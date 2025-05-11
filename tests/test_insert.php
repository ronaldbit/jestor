<?php
try {
    require '../src/jestor.php'; // Incluye la clase
    $conn = new JESTOR('localhost', 'root', '', 'json_example');
    echo "Conexión exitosa.\n";
} catch (Exception $e) {
    echo "Error de conexión: " . $e->getMessage();
    exit;
}

try {
    // Insertar un nuevo usuario
    $sql = "INSERT INTO Usuarios (nombre, correo, password, rol) VALUES (:nombre, :correo, :password, :rol)";
    
    $stmt = $conn->prepare($sql);
    
    // Definir los valores
    $datos = [
        ':nombre' => 'Mariana Jauana',
        ':correo' => 'carlos@mail.com',
        ':password' => password_hash('12345', PASSWORD_DEFAULT),
        ':rol' => 'vendedor'
    ];

    // Ejecutar la inserción
    $stmt->execute($datos);

    echo "Usuario insertado correctamente.\n";
} catch (Exception $e) {
    echo "Error al insertar: " . $e->getMessage();
}
