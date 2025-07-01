# 🛠 Instalación de Jestor

1. Clona o descarga el repositorio Mopla con las extensiones.
2. Ubica `jestor.php` dentro de `/ext/jestor/`.
3. Incluye en tu proyecto:

```php
require_once 'jestor/src/jestor.php';
$db = new Jestor('data/midb.json');
