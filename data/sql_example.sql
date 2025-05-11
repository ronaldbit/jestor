-- Base de datos: TiendaOnline

CREATE TABLE `Usuarios` (
  `idUsuario` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `correo` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `rol` ENUM('admin', 'cliente', 'vendedor') DEFAULT 'cliente',
  PRIMARY KEY (`idUsuario`),
  UNIQUE KEY `correo_UNIQUE` (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `Usuarios` (`nombre`, `correo`, `password`, `rol`) VALUES
('Ronald', 'ronald@mail.com', '12345', 'admin'),
('Vanesa', 'vanesa@mail.com', '54321', 'cliente');

CREATE TABLE `Productos` (
  `idProducto` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  `precio` DECIMAL(10,2) NOT NULL CHECK (`precio` > 0),
  `stock` INT DEFAULT 0 CHECK (`stock` >= 0),
  `estado` ENUM('activo', 'inactivo', 'descontinuado') DEFAULT 'activo',
  PRIMARY KEY (`idProducto`),
  INDEX `idx_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `Productos` (`nombre`, `precio`, `stock`, `estado`) VALUES
('Laptop', 2500.00, 10, 'activo'),
('Mouse', 25.00, 200, 'activo'),
('Teclado', 45.00, 150, 'inactivo');

CREATE TABLE `Pedidos` (
  `idPedido` INT NOT NULL AUTO_INCREMENT,
  `idUsuario` INT NOT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` ENUM('pendiente', 'procesado', 'cancelado') DEFAULT 'pendiente',
  PRIMARY KEY (`idPedido`),
  FOREIGN KEY (`idUsuario`) REFERENCES `Usuarios`(`idUsuario`)
  ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `Pedidos` (`idUsuario`, `fecha`, `estado`) VALUES
(1, '2025-04-01 10:00:00', 'procesado'),
(2, '2025-04-02 12:30:00', 'pendiente');

CREATE TABLE `DetallePedido` (
  `idDetalle` INT NOT NULL AUTO_INCREMENT,
  `idPedido` INT NOT NULL,
  `idProducto` INT NOT NULL,
  `cantidad` INT NOT NULL CHECK (`cantidad` > 0),
  `precio_unitario` DECIMAL(10,2) NOT NULL CHECK (`precio_unitario` >= 0),
  PRIMARY KEY (`idDetalle`),
  FOREIGN KEY (`idPedido`) REFERENCES `Pedidos`(`idPedido`)
  ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`idProducto`) REFERENCES `Productos`(`idProducto`)
  ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `DetallePedido` (`idPedido`, `idProducto`, `cantidad`, `precio_unitario`) VALUES
(1, 1, 1, 2500.00),
(1, 2, 2, 25.00),
(2, 3, 1, 45.00);
