<?php
try {
    require '../src/jestor.php'; // Incluye la clase
    $conn = new JESTOR('localhost', 'root', '', 'json_example');

    // Establecer el modo de error para lanzar excepciones
    $conn->setAttribute(JESTOR::ATTR_ERRMODE, JESTOR::ERRMODE_EXCEPTION);


    echo "Conexión exitosa."."<br>";
} catch (JESTORException $e) {
    echo "Error de conexión: " . $e->getMessage();
    exit;
}

try {
    // Usando fetch_all
    $resultado_all = $conn->query("SELECT * FROM Usuarioss");

    if ($resultado_all === false) {
        echo "Error en la consulta.";
    } else {
        $todos = $resultado_all->fetch_all(MYSQLI_ASSOC); // Requiere MYSQLI_ASSOC
        foreach ($todos as $usuario) {
            echo "Usuario (all): " . $usuario['nombre'] . "<br>";
        }
    }

    // Ver el número total de filas
    echo "Total rows all: " . $resultado_all->num_rows(). "<br>";

    // Usando fetch_assoc fila por fila
    $resultado_assoc = $conn->query("SELECT idUsuario, nombre FROM Usuarios");

    if ($resultado_assoc === false) {
        echo "Error en la consulta.";
    } else {
        while ($fila = $resultado_assoc->fetch_assoc()) {
            echo "Usuario (assoc): " . $fila['nombre'] . "<br>";
        }
    }
 
    // Obtiene la primera fila
    $resultado_one = $conn->query("SELECT idUsuario, nombre FROM Usuarios");
    $row1 = $resultado_one->fetch();
    echo "ID: " . $row1['idUsuario'] . " - Name: " . $row1['nombre'] . "<br>";

} catch (JESTORException $e) {
    echo "Error al consultar: " . $e->getMessage();
}
