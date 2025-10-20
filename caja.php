<?php

include ('./conf.php');

if ($mysqli->connect_error) {
    die(json_encode(["success" => false, "error" => "Error de conexión"]));
}

$accion = $_POST['accion'];

if ($accion === 'abrir') {
    $fecha = date("Y-m-d");
    $hora = date("H:i:s");
    $monto_inicial = $_POST['monto_inicial'];
    $id_usuario = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : null;

    // Verificación básica
    if (!$id_usuario) {
        echo json_encode(["success" => false, "error" => "Falta el id_usuario"]);
        exit;
    }

    $stmt = $mysqli->prepare("INSERT INTO caja (fecha, hora_inicio, monto_inicial, estado, id_usuario) VALUES (?, ?, ?, 'abierta', ?)");
    $stmt->bind_param("ssdi", $fecha, $hora, $monto_inicial, $id_usuario);

    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        echo json_encode([
            "success" => true,
            "id" => $id,
            "fecha" => $fecha,
            "hora_inicio" => $hora,
            "monto_inicial" => $monto_inicial,
            "estado" => "abierta",
            "id_usuario" => $id_usuario
        ]);
    } else {
        echo json_encode(["success" => false, "error" => "No se pudo abrir caja."]);
    }

    $stmt->close();
}

elseif ($accion === 'mostrar') {
    $id = $_POST['id_caja'];
    $result = $mysqli->query("SELECT * FROM caja WHERE id = $id");
    if ($row = $result->fetch_assoc()) {
        echo json_encode(["success" => true] + $row);
    } else {
        echo json_encode(["success" => false, "error" => "Caja no encontrada"]);
    }
}

elseif ($accion === 'obtener_movimientos') {
    $id = $_POST['id_caja'];
    
    // --- Conexión a esquema restroom ---
    $mysqli_restroom = new mysqli($server, $user, $pass, 'restroom');
    if ($mysqli_restroom->connect_error) {
        echo json_encode(["success" => false, "error" => "Error al conectar con esquema restroom"]);
        exit;
    }

    // Total baños
    $stmt_bano = $mysqli_restroom->prepare("SELECT SUM(valor) AS total_bano FROM restroom WHERE id_caja = ?");
    $stmt_bano->bind_param("i", $id);
    $stmt_bano->execute();
    $monto_bano = $stmt_bano->get_result()->fetch_assoc()['total_bano'] ?? 0;
    $stmt_bano->close();

    // Total custodia
    $stmt_cust = $mysqli_restroom->prepare("SELECT SUM(valor) AS total_custodia FROM custodias WHERE id_caja = ?");
    $stmt_cust->bind_param("i", $id);
    $stmt_cust->execute();
    $monto_custodia = $stmt_cust->get_result()->fetch_assoc()['total_custodia'] ?? 0;
    $stmt_cust->close();

    $mysqli_restroom->close();

    // --- Parking y Andenes ---
    // Parking
    $stmt_parking = $mysqli->prepare("SELECT SUM(valor) AS total_parking FROM movParking WHERE id_caja = ? AND tipo = 'Parking'");
    $stmt_parking->bind_param("i", $id);
    $stmt_parking->execute();
    $monto_parking = $stmt_parking->get_result()->fetch_assoc()['total_parking'] ?? 0;
    $stmt_parking->close();

    // Andenes
    $stmt_anden = $mysqli->prepare("SELECT SUM(valor) AS total_anden FROM movParking WHERE id_caja = ? AND tipo = 'Anden'");
    $stmt_anden->bind_param("i", $id);
    $stmt_anden->execute();
    $monto_andenes = $stmt_anden->get_result()->fetch_assoc()['total_anden'] ?? 0;
    $stmt_anden->close();

    echo json_encode([
        "success" => true,
        "monto_bano" => $monto_bano,
        "monto_custodia" => $monto_custodia,
        "monto_parking" => $monto_parking,
        "monto_andenes" => $monto_andenes
    ]);
}

elseif ($accion === 'cerrar') {
    $id = $_POST['id_caja'];
    $hora_cierre = date("H:i:s");

    // --- Conexión a esquema restroom (baños + custodias) ---
    $mysqli_restroom = new mysqli($server, $user, $pass, 'restroom');
    if ($mysqli_restroom->connect_error) {
        echo json_encode(["success" => false, "error" => "Error al conectar con esquema restroom"]);
        exit;
    }

    // Total baños
    $stmt_bano = $mysqli_restroom->prepare("SELECT SUM(valor) AS total_bano FROM restroom WHERE id_caja = ?");
    $stmt_bano->bind_param("i", $id);
    $stmt_bano->execute();
    $monto_bano = $stmt_bano->get_result()->fetch_assoc()['total_bano'] ?? 0;
    $stmt_bano->close();

    // Total custodia
    $stmt_cust = $mysqli_restroom->prepare("SELECT SUM(valor) AS total_custodia FROM custodias WHERE id_caja = ?");
    $stmt_cust->bind_param("i", $id);
    $stmt_cust->execute();
    $monto_custodia = $stmt_cust->get_result()->fetch_assoc()['total_custodia'] ?? 0;
    $stmt_cust->close();

    $mysqli_restroom->close();

    // --- Parking y Andenes (mismo esquema de caja) ---
    // Parking
    $stmt_parking = $mysqli->prepare("SELECT SUM(valor) AS total_parking FROM movParking WHERE id_caja = ? AND tipo = 'Parking'");
    $stmt_parking->bind_param("i", $id);
    $stmt_parking->execute();
    $monto_parking = $stmt_parking->get_result()->fetch_assoc()['total_parking'] ?? 0;
    $stmt_parking->close();

    // Andenes
    $stmt_anden = $mysqli->prepare("SELECT SUM(valor) AS total_anden FROM movParking WHERE id_caja = ? AND tipo = 'Anden'");
    $stmt_anden->bind_param("i", $id);
    $stmt_anden->execute();
    $monto_andenes = $stmt_anden->get_result()->fetch_assoc()['total_anden'] ?? 0;
    $stmt_anden->close();

    // --- Update de caja ---
    $stmt = $mysqli->prepare("UPDATE caja SET hora_cierre = ?, estado = 'cerrada', monto_bano = ?, monto_custodia = ?, monto_parking = ?, monto_andenes = ? WHERE id = ?");
    $stmt->bind_param("siiiii", $hora_cierre, $monto_bano, $monto_custodia, $monto_parking, $monto_andenes, $id);
    if ($stmt->execute()) {
        $result = $mysqli->query("SELECT * FROM caja WHERE id = $id");
        $row = $result->fetch_assoc();
        echo json_encode(["success" => true] + $row);
    } else {
        echo json_encode(["success" => false, "error" => "No se pudo cerrar caja."]);
    }
}

$mysqli->close();
?>