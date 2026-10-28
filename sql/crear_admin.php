<?php
/**
 * Genera el SQL para crear (o actualizar) el usuario administrador, con su hash bcrypt
 * calculado en tu máquina.
 *
 * POR QUÉ EXISTE: los datos iniciales NO traen ningún administrador. Si lo trajeran, su hash
 * quedaría publicado en el repositorio de GitHub y cualquiera podría entrar al panel: un hash
 * bcrypt de una contraseña conocida no protege nada, porque la contraseña ya es pública.
 *
 * QUÉ HACE Y QUÉ NO HACE:
 *   - Calcula el hash localmente. No se conecta a internet ni a ninguna base de datos.
 *   - No escribe la contraseña ni el hash en ningún archivo ni en ningún log.
 *   - Solo imprime en pantalla el SQL que tenés que ejecutar una vez.
 *
 * USO (desde la raíz del proyecto):
 *
 *   php sql/crear_admin.php
 *       Genera una contraseña fuerte al azar y la muestra UNA vez. Es la opción recomendada.
 *
 *   php sql/crear_admin.php --escribir
 *       Te deja escribir la tuya. OJO: se ve en pantalla mientras la escribís.
 *
 *   php sql/crear_admin.php --email=otro@dominio.com --nombre="Mi Nombre"
 *       Cambia el correo o el nombre (por defecto: admin@capibarato.pe / Administrador).
 *
 * Si "php" no está en el PATH, usá la ruta completa: C:\xampp\php\php.exe sql/crear_admin.php
 *
 * Después pegá el SQL que imprime en el SQL Editor de Supabase y ejecutalo.
 * NO guardes ese SQL en el repositorio ni lo compartas: contiene el hash de tu contraseña real.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Este script solo se ejecuta desde la línea de comandos.\n");
}

const LARGO_MINIMO = 12;

$opciones = getopt('', ['escribir', 'email::', 'nombre::']);

$email = $opciones['email'] ?? 'admin@capibarato.pe';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    salir("El correo '$email' no es válido.");
}

$nombre = $opciones['nombre'] ?? 'Administrador';
if ($nombre === '' || mb_strlen($nombre) > 100) {
    salir('El nombre debe tener entre 1 y 100 caracteres.');
}

fwrite(STDOUT, "\n== Administrador de Capi-Barato ==\n\n");
fwrite(STDOUT, "Correo: $email\n");
fwrite(STDOUT, "Nombre: $nombre\n\n");

$generada = !array_key_exists('escribir', $opciones);

if ($generada) {
    $clave = generarClave();
} else {
    fwrite(STDOUT, "AVISO: lo que escribas se va a ver en pantalla. Si hay alguien mirando,\n");
    fwrite(STDOUT, "cortá con Ctrl+C y volvé a correrlo sin --escribir para que se genere sola.\n\n");
    fwrite(STDOUT, 'Contraseña (mínimo ' . LARGO_MINIMO . " caracteres): ");
    $clave = rtrim((string) fgets(STDIN), "\r\n");

    if (mb_strlen($clave) < LARGO_MINIMO) {
        salir('Muy corta: tiene que tener al menos ' . LARGO_MINIMO . ' caracteres.');
    }
    fwrite(STDOUT, 'Repetila: ');
    $repetida = rtrim((string) fgets(STDIN), "\r\n");
    if (!hash_equals($clave, $repetida)) {
        salir('Las dos contraseñas no coinciden.');
    }
}

$hash = password_hash($clave, PASSWORD_DEFAULT);
if ($hash === false) {
    salir('No se pudo calcular el hash.');
}
// Comprobación real: el hash tiene que validar contra la contraseña recién elegida.
if (!password_verify($clave, $hash)) {
    salir('El hash generado no verifica. No uses este resultado.');
}

$emailSql = str_replace("'", "''", $email);
$nombreSql = str_replace("'", "''", $nombre);

fwrite(STDOUT, "\n--------------------------------------------------------------------\n");
if ($generada) {
    fwrite(STDOUT, "CONTRASEÑA GENERADA. Se muestra una sola vez: guardala ahora.\n\n");
    fwrite(STDOUT, '    ' . $clave . "\n\n");
    fwrite(STDOUT, "--------------------------------------------------------------------\n");
}
fwrite(STDOUT, "Ejecutá este SQL una vez en el SQL Editor de Supabase.\n");
fwrite(STDOUT, "Sirve igual para crear el administrador o para cambiarle la contraseña.\n");
fwrite(STDOUT, "NO lo guardes en el repositorio.\n");
fwrite(STDOUT, "--------------------------------------------------------------------\n\n");

fwrite(STDOUT, "INSERT INTO usuarios (nombre, email, password_hash, rol, estado)\n");
fwrite(STDOUT, "VALUES ('$nombreSql', '$emailSql', '$hash', 'admin', 'activo')\n");
fwrite(STDOUT, "ON CONFLICT (email) DO UPDATE\n");
fwrite(STDOUT, "    SET password_hash = EXCLUDED.password_hash,\n");
fwrite(STDOUT, "        nombre        = EXCLUDED.nombre,\n");
fwrite(STDOUT, "        rol           = 'admin',\n");
fwrite(STDOUT, "        estado        = 'activo';\n\n");

fwrite(STDOUT, "Para MySQL (XAMPP), la misma operación es:\n\n");
fwrite(STDOUT, "INSERT INTO usuarios (nombre, email, password_hash, rol, estado)\n");
fwrite(STDOUT, "VALUES ('$nombreSql', '$emailSql', '$hash', 'admin', 'activo')\n");
fwrite(STDOUT, "ON DUPLICATE KEY UPDATE\n");
fwrite(STDOUT, "    password_hash = VALUES(password_hash),\n");
fwrite(STDOUT, "    nombre        = VALUES(nombre),\n");
fwrite(STDOUT, "    rol           = 'admin',\n");
fwrite(STDOUT, "    estado        = 'activo';\n\n");

exit(0);


/** Contraseña aleatoria fuerte, con random_int (generador criptográfico). */
function generarClave(int $largo = 20): string
{
    // Sin caracteres ambiguos (O/0, l/1) para que se pueda transcribir sin errores.
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%&*?';
    $maximo = strlen($alfabeto) - 1;
    $clave = '';
    for ($i = 0; $i < $largo; $i++) {
        $clave .= $alfabeto[random_int(0, $maximo)];
    }
    return $clave;
}

function salir(string $mensaje): void
{
    fwrite(STDERR, "\nERROR: $mensaje\n");
    exit(1);
}
