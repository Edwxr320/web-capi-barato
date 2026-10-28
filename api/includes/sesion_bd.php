<?php
/**
 * Manejador de sesiones guardadas en la base de datos.
 *
 * POR QUÉ EXISTE: el manejador de archivos de PHP guarda cada sesión en el disco del servidor.
 * En un entorno serverless (Vercel) cada petición puede atenderla una instancia distinta, con su
 * propio disco temporal que además se borra. Con sesiones en archivos la persona perdería la sesión
 * al azar y —peor— el token CSRF vive en $_SESSION, así que TODA operación de escritura fallaría
 * de forma intermitente: login, carrito, checkout y panel.
 *
 * Guardar la sesión en la base la hace compartida entre instancias y duradera, que es justo lo que
 * el entorno no garantiza.
 *
 * Se usa el MISMO manejador en XAMPP y en producción, para que lo que se prueba en local sea
 * exactamente lo que corre publicado.
 *
 * Diferencia conocida contra el manejador de archivos: PHP bloquea el archivo de sesión mientras
 * la atiende, serializando las peticiones de una misma persona. Acá no hay bloqueo: dos peticiones
 * simultáneas de la misma sesión pueden pisarse en lo que escriban. Para esta aplicación es
 * inofensivo (en la sesión solo se guardan id de usuario, nombre, email, rol y token CSRF, que no
 * cambian entre peticiones), y es el comportamiento habitual en serverless.
 */

require_once __DIR__ . '/db.php';

final class SesionEnBaseDeDatos implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private ?PDO $pdo = null;

    private function bd(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = obtenerConexion();
        }
        return $this->pdo;
    }

    public function open(string $ruta, string $nombre): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $stmt = $this->bd()->prepare(
            'SELECT datos FROM sesiones WHERE id = :id AND fecha_expiracion > ' . ($this->ahora())
        );
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch();
        return $fila ? (string) $fila['datos'] : '';
    }

    public function write(string $id, string $datos): bool
    {
        $vence = $this->expiracion();
        // UPSERT: la sintaxis es lo único que difiere entre los dos motores.
        $sql = esPostgres()
            ? 'INSERT INTO sesiones (id, datos, fecha_expiracion) VALUES (:id, :datos, ' . $vence . ')
               ON CONFLICT (id) DO UPDATE SET datos = EXCLUDED.datos, fecha_expiracion = EXCLUDED.fecha_expiracion'
            : 'INSERT INTO sesiones (id, datos, fecha_expiracion) VALUES (:id, :datos, ' . $vence . ')
               ON DUPLICATE KEY UPDATE datos = VALUES(datos), fecha_expiracion = VALUES(fecha_expiracion)';
        return $this->bd()->prepare($sql)->execute(['id' => $id, 'datos' => $datos]);
    }

    public function destroy(string $id): bool
    {
        return $this->bd()->prepare('DELETE FROM sesiones WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Limpieza de sesiones vencidas. PHP la invoca al azar (session.gc_probability); como la
     * condición es por fecha_expiracion, una sesión vencida nunca se lee aunque la fila siga ahí.
     */
    #[\ReturnTypeWillChange]
    public function gc(int $vidaMaxima)
    {
        $this->bd()->exec('DELETE FROM sesiones WHERE fecha_expiracion <= ' . $this->ahora());
        return true;
    }

    /**
     * Con session.use_strict_mode=1 PHP pregunta si el id existe antes de aceptarlo. Responder que
     * no para los ids desconocidos es lo que impide la fijación de sesión con un id inventado.
     */
    public function validateId(string $id): bool
    {
        $stmt = $this->bd()->prepare(
            'SELECT 1 FROM sesiones WHERE id = :id AND fecha_expiracion > ' . $this->ahora()
        );
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetch();
    }

    /** Solo se tocó la marca de tiempo: renueva el vencimiento sin reescribir los datos. */
    public function updateTimestamp(string $id, string $datos): bool
    {
        return $this->bd()
            ->prepare('UPDATE sesiones SET fecha_expiracion = ' . $this->expiracion() . ' WHERE id = :id')
            ->execute(['id' => $id]);
    }

    private function ahora(): string
    {
        return 'CURRENT_TIMESTAMP';
    }

    /** CURRENT_TIMESTAMP + session.gc_maxlifetime segundos, en la sintaxis de cada motor. */
    private function expiracion(): string
    {
        $segundos = (int) (ini_get('session.gc_maxlifetime') ?: 1440);
        return esPostgres()
            ? "CURRENT_TIMESTAMP + INTERVAL '$segundos seconds'"
            : "DATE_ADD(CURRENT_TIMESTAMP, INTERVAL $segundos SECOND)";
    }
}

/** Registra el manejador. Hay que llamarla ANTES de session_start(). */
function registrarManejadorSesiones(): void
{
    static $registrado = false;
    if ($registrado || ALMACEN_SESIONES !== 'bd') {
        return;
    }
    session_set_save_handler(new SesionEnBaseDeDatos(), true);
    $registrado = true;
}
