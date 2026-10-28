<?php
/**
 * Límite de intentos fallidos de login, guardado en la base de datos.
 *
 * Se cuenta por combinación IP + correo. Tras LOGIN_MAX_INTENTOS fallos dentro de
 * LOGIN_VENTANA_SEGUNDOS, ese correo queda bloqueado desde esa IP hasta que pase la ventana
 * (respuesta 429 con Retry-After), incluso si la contraseña siguiente es la correcta.
 * Un login exitoso borra el contador.
 *
 * POR QUÉ EN LA BASE Y YA NO EN ARCHIVOS: antes los contadores vivían en el directorio temporal
 * del servidor. En serverless cada instancia tiene su propio directorio temporal y además se borra,
 * así que el atacante obtenía intentos nuevos con solo caer en otra instancia: la protección era
 * efectivamente nula. En la base el contador es único y compartido.
 *
 * Esto además elimina la limitación que el proyecto ya tenía documentada ("el contador se pierde
 * si se limpia el directorio temporal"). Lo que NO cambia: no frena a quien rote muchas IP.
 *
 * La IP se guarda con hash junto al correo: la tabla no expone direcciones ni correos en claro.
 */

require_once __DIR__ . '/db.php';

function limiteMaxIntentos(): int
{
    return defined('LOGIN_MAX_INTENTOS') ? max(1, (int) LOGIN_MAX_INTENTOS) : 5;
}

function limiteVentanaSegundos(): int
{
    return defined('LOGIN_VENTANA_SEGUNDOS') ? max(1, (int) LOGIN_VENTANA_SEGUNDOS) : 900;
}

/**
 * IP real de quien hace la petición.
 *
 * Detrás de un proxy (Vercel), REMOTE_ADDR es la IP del PROXY, no la de la persona: usarla haría
 * que todo el mundo comparta el mismo contador y que un atacante bloquee el login de todos.
 * Por eso, SOLO cuando se corre en Vercel, se toma la primera IP de la cadena X-Forwarded-For,
 * que es la que agrega el propio proxy. En XAMPP se usa REMOTE_ADDR como siempre: una cabecera
 * X-Forwarded-For enviada a mano no se tiene en cuenta, para que nadie pueda falsear su IP y
 * saltarse el límite.
 */
function ipCliente(): string
{
    if (entorno('VERCEL') !== null) {
        $cadena = $_SERVER['HTTP_X_VERCEL_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($cadena !== '') {
            $primera = trim(explode(',', $cadena)[0]);
            if (filter_var($primera, FILTER_VALIDATE_IP)) {
                return $primera;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
}

/** Identificador opaco de la combinación IP + correo (no guarda ninguno de los dos en claro). */
function claveIntentos(string $email): string
{
    return hash('sha256', ipCliente() . '|' . $email);
}

/** Momentos de los fallos todavía dentro de la ventana, del más viejo al más nuevo. */
function fallosVigentes(string $email): array
{
    $stmt = obtenerConexion()->prepare(
        'SELECT momento FROM intentos_login WHERE clave = :clave AND momento > :desde ORDER BY momento'
    );
    $stmt->execute([
        'clave' => claveIntentos($email),
        'desde' => date('Y-m-d H:i:s', time() - limiteVentanaSegundos()),
    ]);
    return array_column($stmt->fetchAll(), 'momento');
}

/** Corta con 429 si ya se alcanzó el máximo de fallos. */
function verificarLimiteLogin(string $email): void
{
    $fallos = fallosVigentes($email);
    if (count($fallos) >= limiteMaxIntentos()) {
        $masViejo = strtotime((string) $fallos[0]);
        $espera = max(1, $masViejo + limiteVentanaSegundos() - time());
        header('Retry-After: ' . $espera);
        $minutos = (int) ceil($espera / 60);
        responderError("Demasiados intentos fallidos. Esperá $minutos minuto(s) e intentá de nuevo.", 429);
    }
}

function registrarFalloLogin(string $email): void
{
    $pdo = obtenerConexion();
    $pdo->prepare('INSERT INTO intentos_login (clave, momento) VALUES (:clave, :momento)')
        ->execute(['clave' => claveIntentos($email), 'momento' => date('Y-m-d H:i:s')]);

    // Limpieza oportunista de registros ya vencidos, para que la tabla no crezca sin control.
    $pdo->prepare('DELETE FROM intentos_login WHERE momento <= :desde')
        ->execute(['desde' => date('Y-m-d H:i:s', time() - limiteVentanaSegundos())]);
}

function limpiarFallosLogin(string $email): void
{
    obtenerConexion()
        ->prepare('DELETE FROM intentos_login WHERE clave = :clave')
        ->execute(['clave' => claveIntentos($email)]);
}
