<?php

/**
 * Clase de excepción personalizada para operaciones de JESTOR.
 */
class JESTORException extends Exception {}

/**
 * JESTOR es un mini motor de base de datos que emula consultas SQL sobre archivos JSON.
 * Es ideal para entornos sin bases de datos o para un desarrollo rápido sin dependencias.
 */
class JESTOR {
    /** @var string El host de la base de datos. Actualmente no se utiliza, pero se mantiene por compatibilidad de API. */
    private $host;
    /** @var string El nombre de usuario para la autenticación de la base de datos. */
    private $user;
    /** @var string La contraseña para la autenticación de la base de datos. */
    private $pass;
    /** @var string El nombre de la base de datos (archivo JSON). */
    private $db;
    /** @var array La representación en memoria de la base de datos JSON. */
    private $data = [];
    /** @var string La ruta al archivo de la base de datos JSON. */
    private $file;
    /** @var string|null Mensaje de error de conexión, si lo hay. */
    public $connect_error = null;

    /** @var array Un array para mantener los parámetros vinculados para sentencias preparadas. */
    private $boundParams = [];

    /** @var int|null El ID de la última fila insertada. */
    private $lastInsertId = null;

    // Constantes de manejo de errores
    const ATTR_ERRMODE    = 1;
    const ATTR_FETCH_MODE = 2;

    // Valores de modo de error
    const ERRMODE_SILENT    = 0;
    const ERRMODE_WARNING   = 1;
    const ERRMODE_EXCEPTION = 2;

    // Valores de modo de obtención
    const FETCH_ASSOC = 1;
    const FETCH_NUM   = 2;
    const FETCH_BOTH  = 3;

    /** @var array Un array para mantener los atributos de la conexión. */
    private $attributes = [];
    /** @var string El último mensaje de error. */
    private $lastError = '';

    /**
     * Constructor de JESTOR.
     *
     * @param string $host El host de la base de datos.
     * @param string $user El nombre de usuario para la autenticación.
     * @param string $pass La contraseña para la autenticación.
     * @param string $db El nombre de la base de datos (archivo JSON sin extensión).
     * @param array  $options Un array de opciones de conexión.
     */
    public function __construct($host, $user, $pass, $db, $options = []) {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->db = $db;
        $this->file = "../data/".$db.'.json';

        if (!file_exists($this->file)) {
            $this->connect_error = "Base de datos '$db' no encontrada."."<br>";
            return;
        }

        $raw = file_get_contents($this->file);
        $this->data = json_decode($raw, true);
        if (!$this->data) {
            $this->connect_error = "Error al leer el archivo JSON."."<br>";
            return;
        }

        // Validación de usuario
        $usuarios = $this->data['db']['users'] ?? [];
        $valido = false;
        foreach ($usuarios as $u) {
            if ($u['user'] === $user && $u['pass'] === $pass) {
                $valido = true;
                break;
            }
        }

        if (!$valido) {
            $this->connect_error = "Credenciales inválidas para '$user'."."<br>";
            $this->data = []; // evitar uso posterior
        }

        // Valores por defecto
        $this->attributes = [
            self::ATTR_ERRMODE    => self::ERRMODE_SILENT,
            self::ATTR_FETCH_MODE => self::FETCH_ASSOC
        ];

        // Aplicar configuraciones iniciales
        foreach ($options as $attr => $val) {
            $this->setAttribute($attr, $val);
        }
    }

    /**
     * Establece un atributo de conexión.
     *
     * @param int $attribute El atributo a establecer (p. ej., ATTR_ERRMODE).
     * @param mixed $value El valor para el atributo.
     * @throws InvalidArgumentException Si el atributo o el valor no son válidos.
     */
    public function setAttribute($attribute, $value) {
        switch ($attribute) {
            case self::ATTR_ERRMODE:
                if (!in_array($value, [self::ERRMODE_SILENT, self::ERRMODE_WARNING, self::ERRMODE_EXCEPTION])) {
                    throw new InvalidArgumentException("Valor inválido para ERRMODE");
                }
                break;

            case self::ATTR_FETCH_MODE:
                if (!in_array($value, [self::FETCH_ASSOC, self::FETCH_NUM, self::FETCH_BOTH])) {
                    throw new InvalidArgumentException("Valor inválido para FETCH_MODE");
                }
                break;

            default:
                throw new InvalidArgumentException("Atributo no reconocido");
        }

        $this->attributes[$attribute] = $value;
    }

    /**
     * Obtiene un atributo de conexión.
     *
     * @param int $attribute El atributo a obtener.
     * @return mixed El valor del atributo.
     * @throws InvalidArgumentException Si el atributo no es válido.
     */
    public function getAttribute($attribute) {
        if (!array_key_exists($attribute, $this->attributes)) {
            throw new InvalidArgumentException("Atributo no válido");
        }

        return $this->attributes[$attribute];
    }

    /**
     * Obtiene el último mensaje de error.
     *
     * @return string El último mensaje de error.
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Dispara un error basado en el modo de error configurado.
     *
     * @param string $message El mensaje de error.
     * @return bool|void Devuelve false en los modos SILENT y WARNING, lanza una excepción en el modo EXCEPTION.
     * @throws JESTORException En modo EXCEPTION.
     */
    public function triggerError($message) {
        $mode = $this->getAttribute(self::ATTR_ERRMODE);

        switch ($mode) {
            case self::ERRMODE_SILENT:
                $this->lastError = $message;
                return false;

            case self::ERRMODE_WARNING:
                trigger_error($message, E_USER_WARNING);
                return false;

            case self::ERRMODE_EXCEPTION:
                throw new JESTORException($message);
        }
    }

    /**
     * Formatea una fila según el modo de obtención actual.
     *
     * @param array $row La fila a formatear.
     * @return array La fila formateada.
     */
    public function fetchMock(array $row) {
        $mode = $this->getAttribute(self::ATTR_FETCH_MODE);

        switch ($mode) {
            case self::FETCH_ASSOC:
                return $row; // ya es asociativo
            case self::FETCH_NUM:
                return array_values($row); // solo índices numéricos
            case self::FETCH_BOTH:
                $both = $row;
                foreach (array_values($row) as $i => $val) {
                    $both[$i] = $val;
                }
                return $both;
            default:
                return $row;
        }
    }

    /**
     * Método público para acceder a los datos de la base de datos (JSON).
     *
     * @return array Los datos de la base de datos.
     */
    public function getData() {
        return $this->data;
    }

    /**
     * Ejecuta una consulta SQL.
     *
     * @param string $sql La consulta SQL a ejecutar.
     * @return JESTORResult|bool El objeto de resultado para consultas SELECT, true para INSERT/UPDATE/DELETE exitosos, false en caso de fallo.
     */
    public function query($sql) {
        if (stripos($sql, 'SELECT') === 0) {
            return $this->handleSelect($sql);
        }

        if (stripos($sql, 'INSERT') === 0) {
            return $this->handleInsert($sql);
        }

        if (stripos($sql, 'UPDATE') === 0) {
            return $this->handleUpdate($sql);
        }

        if (stripos($sql, 'DELETE') === 0) {
            return $this->handleDelete($sql);
        }

        return false;
    }

    /**
     * Maneja las consultas SELECT.
     *
     * @param string $sql La consulta SELECT.
     * @return JESTORResult|bool Un objeto de resultado o false en caso de fallo.
     */
    public function handleSelect($sql) {
        preg_match('/SELECT\s+(.*?)\s+FROM\s+([a-zA-Z_]+)/i', $sql, $matches);
        if (!$matches || !isset($matches[1]) || !isset($matches[2])) {
            return false;
        }

        $columnas = $matches[1];
        $tabla = $matches[2];

        if (!isset($this->data['data'][$tabla]['rows'])) {
            $this->triggerError("Tabla '$tabla' no encontrada en JSON");
            return false;
        }

        // Obtener las filas
        $rows = $this->data['data'][$tabla]['rows'];

        // Si no se especificaron columnas, devolver todas
        if ($columnas == '*') {
            return new JESTORResult($rows);
        }

        // Filtrar solo las columnas seleccionadas
        $columnasSeleccionadas = explode(',', $columnas);
        $columnasSeleccionadas = array_map('trim', $columnasSeleccionadas);

        $resultadosFiltrados = [];
        foreach ($rows as $row) {
            $rowFiltrado = [];
            foreach ($columnasSeleccionadas as $col) {
                if (isset($row[$col])) {
                    $rowFiltrado[$col] = $row[$col];
                }
            }
            $resultadosFiltrados[] = $rowFiltrado;
        }

        return new JESTORResult($resultadosFiltrados);
    }

    /**
     * Maneja las consultas INSERT.
     *
     * @param string $sql La consulta INSERT.
     * @return bool True en caso de éxito, false en caso de fallo.
     * @throws Exception Si la validación falla.
     */
    public function handleInsert($sql) {
        preg_match('/INTO\s+(\w+)\s*\((.*?)\)\s*VALUES\s*\((.*?)\)/i', $sql, $matches);
        if (!$matches) return false;

        $table = $matches[1];
        $columns = array_map('trim', explode(',', $matches[2]));
        $valuesPart = $matches[3];

        preg_match_all('/:\w+/', $valuesPart, $valueMatches);
        $placeholders = $valueMatches[0];
        $values = [];
        foreach ($placeholders as $placeholder) {
            $realValue = $this->getRealValue($placeholder);
            if ($realValue === null) {
                echo "[DEBUG] No se encontró valor para: $placeholder\n";
            }
            $values[] = $realValue;
        }

        if (strpos($valuesPart, ':') !== false) {
            preg_match_all('/:\w+/', $valuesPart, $valueMatches);
            $placeholders = $valueMatches[0];

            $values = [];
            foreach ($placeholders as $placeholder) {
                $realValue = $this->getRealValue($placeholder);
                $values[] = $realValue;
            }
        } else {
            $values = array_map(function($v) {
                return trim(trim($v), "'\"");
            }, explode(',', $valuesPart));
        }

        if (!isset($this->data['data'][$table]['rows'])) return false;

        $colsDef = $this->data['data'][$table]['columns'] ?? [];

        foreach ($colsDef as $col => $def) {
            if (!in_array($col, $columns) && !empty($def['auto_increment'])) {
                $lastId = 0;
                foreach ($this->data['data'][$table]['rows'] as $row) {
                    if (isset($row[$col]) && $row[$col] > $lastId) {
                        $lastId = $row[$col];
                    }
                }
                array_unshift($columns, $col);
                array_unshift($values, $lastId + 1);
                break;
            }
        }

        foreach ($columns as $i => $col) {
            $val = $values[$i] ?? null; 
            $colDef = $colsDef[$col] ?? null;

            if (!$colDef) continue;

            if (!empty($colDef['not_null']) && ($val === null || $val === '')) {
                throw new Exception("[ERROR] El campo '$col' no puede ser NULL.");
            }

            if ($colDef['type'] === 'int' && !is_numeric($val)) {
                throw new Exception("[ERROR] El valor de '$col' debe ser un número entero.");
            }

            if (empty($colDef['auto_increment']) && isset($val) && $val !== null && strlen($val) > $colDef['length']) {
                throw new Exception("[ERROR] El valor de '$col' excede el tamaño máximo de {$colDef['length']} caracteres.");
            }
        }

        $newRow = array_combine($columns, $values);
        $this->data['data'][$table]['rows'][] = $newRow;
        $this->lastInsertId = $lastId + 1;

        file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }

    /**
     * Obtiene el ID de la última fila insertada.
     *
     * @return int|null El último ID insertado.
     */
    public function lastInsertId() {
        return $this->lastInsertId;
    }

    /**
     * Vincula un valor a un parámetro.
     *
     * @param string $key El marcador de posición del parámetro (p. ej., ':nombre').
     * @param mixed $value El valor a vincular.
     */
    public function bindValue($key, $value) {
        $this->boundParams[$key] = $value;
    }

    /**
     * Obtiene el valor real de un marcador de posición.
     *
     * @param string $placeholder El marcador de posición.
     * @return mixed El valor vinculado o null si no se encuentra.
     */
    private function getRealValue($placeholder) {
        return $this->boundParams[$placeholder] ?? null;
    }

    /**
     * Maneja las consultas UPDATE.
     *
     * @param string $sql La consulta UPDATE.
     * @return bool True si se actualizaron filas, false en caso contrario.
     */
    public function handleUpdate($sql) {
        preg_match('/UPDATE\s+(\w+)\s+SET\s+(.*?)\s+WHERE\s+(.*)/i', $sql, $matches);
        if (!$matches) return false;

        [$_, $table, $setClause, $whereClause] = $matches;

        if (!isset($this->data['data'][$table]['rows'])) return false;

        parse_str(str_replace(",", "&", str_replace("=", "=", $setClause)), $setValues);

        $set = [];
        foreach (explode(',', $setClause) as $pair) {
            [$k, $v] = explode('=', $pair);
            $set[trim($k)] = trim($v, " '\"");
        }

        preg_match("/(\w+)\s*=\s*'?(.*?)'?$/", $whereClause, $cond);
        if (!$cond) return false;
        [$_, $colCond, $valCond] = $cond;

        $updated = 0;
        foreach ($this->data['data'][$table]['rows'] as &$row) {
            if (isset($row[$colCond]) && $row[$colCond] == $valCond) {
                foreach ($set as $k => $v) {
                    $row[$k] = $v;
                }
                $updated++;
            }
        }

        file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $updated > 0;
    }

    /**
     * Maneja las consultas DELETE.
     *
     * @param string $sql La consulta DELETE.
     * @return bool True si se eliminaron filas, false en caso contrario.
     */
    public function handleDelete($sql) {
        preg_match('/DELETE\s+FROM\s+(\w+)\s+WHERE\s+(.*)/i', $sql, $matches);
        if (!$matches) return false;

        [$_, $table, $whereClause] = $matches;

        if (!isset($this->data['data'][$table]['rows'])) return false;

        preg_match("/(\w+)\s*=\s*'?(.*?)'?$/", $whereClause, $cond);
        if (!$cond) return false;
        [$_, $colCond, $valCond] = $cond;

        $newRows = [];
        foreach ($this->data['data'][$table]['rows'] as $row) {
            if (!(isset($row[$colCond]) && $row[$colCond] == $valCond)) {
                $newRows[] = $row;
            }
        }

        $deleted = count($this->data['data'][$table]['rows']) - count($newRows);
        $this->data['data'][$table]['rows'] = $newRows;

        file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $deleted > 0;
    }

    /**
     * Prepara una sentencia SQL para su ejecución.
     *
     * @param string $sql La sentencia SQL a preparar.
     * @return JESTORStatement Un objeto de sentencia.
     */
    public function prepare($sql) {
        return new JESTORStatement($this, $sql);
    }
}

if (!defined('MYSQLI_ASSOC')) define('MYSQLI_ASSOC', 1);
if (!defined('MYSQLI_NUM')) define('MYSQLI_NUM', 2);
if (!defined('MYSQLI_BOTH')) define('MYSQLI_BOTH', 3);

/**
 * Representa el conjunto de resultados de una consulta JESTOR.
 */
class JESTORResult {
    /** @var array Las filas del conjunto de resultados. */
    private $rows;
    /** @var int La posición actual en el conjunto de resultados. */
    private $index = 0;

    /**
     * Constructor de JESTORResult.
     *
     * @param array $rows Las filas del conjunto de resultados.
     */
    public function __construct($rows) {
        $this->rows = $rows;
    }
    
    /**
     * Obtiene la siguiente fila del conjunto de resultados.
     *
     * @return array|null La siguiente fila como un array asociativo, o null si no hay más filas.
     */
    public function fetch() {
        if ($this->index < count($this->rows)) {
            return $this->rows[$this->index++];
        }
        return null;
    }
    
    /**
     * Restablece el puntero del conjunto de resultados al principio.
     */
    public function resetIndex() {
        $this->index = 0;
    }
    
    /**
     * Obtiene la siguiente fila como un array asociativo.
     *
     * @return array|null La siguiente fila, o null si no hay más filas.
     */
    public function fetch_assoc() {
        if ($this->index < count($this->rows)) {
            return $this->rows[$this->index++];
        }
        return null;
    }

    /**
     * Devuelve todas las filas del conjunto de resultados.
     *
     * @return array Todas las filas.
     */
    public function all() {
        return $this->rows;
    }

    /**
     * Obtiene todas las filas en el modo especificado.
     *
     * @param int $mode El modo de obtención (MYSQLI_ASSOC, MYSQLI_NUM, o MYSQLI_BOTH).
     * @return array Todas las filas en el formato especificado.
     */
    public function fetchAll($mode = MYSQLI_ASSOC) {
        return $this->fetch_all($mode);
    }

    /**
     * Obtiene todas las filas según el modo especificado.
     *
     * @param int $mode El modo de obtención.
     * @return array Todas las filas.
     */
    public function fetch_all($mode = MYSQLI_ASSOC) {
        if ($mode === MYSQLI_ASSOC) {
            return $this->rows;
        }

        if ($mode === MYSQLI_NUM) {
            return array_map('array_values', $this->rows);
        }

        if ($mode === MYSQLI_BOTH) {
            return array_map(function($row) {
                $num = array_values($row);
                return array_merge($row, $num);
            }, $this->rows);
        }

        return $this->rows;
    }

    /**
     * Obtiene el número de filas en el conjunto de resultados.
     *
     * @return int El número de filas.
     */
    public function num_rows() {
        return count($this->rows);
    }
}

/**
 * Representa una sentencia preparada.
 */
class JESTORStatement {
    /** @var JESTOR El objeto de conexión JESTOR. */
    private $jestor;
    /** @var string La sentencia SQL. */
    private $sql;
    /** @var array Los parámetros vinculados. */
    private $params = [];

    /**
     * Constructor de JESTORStatement.
     *
     * @param JESTOR $jestor El objeto de conexión.
     * @param string $sql La sentencia SQL.
     */
    public function __construct($jestor, $sql) {
        $this->jestor = $jestor;
        $this->sql = $sql;
    }

    /**
     * Ejecuta la sentencia preparada.
     *
     * @param array $params Los parámetros a vincular.
     * @return JESTORResult|bool El resultado de la consulta.
     */
    public function execute($params = []) {
        $sql = $this->sql;
        foreach ($params as $key => $value) {
            $escaped = str_replace("'", "\\'", $value);
            $sql = str_replace($key, "'$escaped'", $sql);
        }

        return $this->jestor->query($sql);
    }

    /**
     * Vincula un valor a un parámetro.
     *
     * @param string $key El marcador de posición del parámetro.
     * @param mixed $value El valor a vincular.
     */
    public function bindValue($key, $value) {
        $this->params[$key] = $value;
    }

    /**
     * Obtiene un resultado de una sentencia preparada.
     *
     * @return null Actualmente no implementado.
     */
    public function fetch() {
        return null;
    }
}
?>