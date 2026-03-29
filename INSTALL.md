# 🛠 Instalación de Jestor

1. Clona o descarga el repositorio con las extensiones.
2. Ubica `jestor.php` dentro de `/src/`.
3. Incluye en tu proyecto:

```php
require_once '/src/jestor.php';
$db = new Jestor('data/midb.json');
