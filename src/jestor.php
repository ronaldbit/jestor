<?php

class JESTORException extends Exception {}

class JESTOR {
    private $host, $user, $pass, $db;
    private $data = [];
    private $file;
    public $connect_error = null;

    private $boundParams = [];

    private $lastInsertId = null;

    //manejo de errores:
    // Constantes de atributos
    const ATTR_ERRMODE    = 1;
    const ATTR_FETCH_MODE = 2;

    // Constantes de valores
    const ERRMODE_SILENT    = 0;
    const ERRMODE_WARNING   = 1;
    const ERRMODE_EXCEPTION = 2;

    const FETCH_ASSOC = 1;
    const FETCH_NUM   = 2;
    const FETCH_BOTH  = 3;

    private $attributes = [];
    private $lastError = '';

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

    //manejo de errore: 
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

    public function getAttribute($attribute) {
        if (!array_key_exists($attribute, $this->attributes)) {
            throw new InvalidArgumentException("Atributo no válido");
        }

        return $this->attributes[$attribute];
    }

    public function getLastError() {
        return $this->lastError;
    }
    
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

 

    // Método público para acceder a los datos de la base de datos (JSON)
    public function getData() {
        return $this->data;
    }

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

    // Método para manejar SELECT
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

    // Método público para manejar INSERT
    public function handleInsert($sql) {
        // Comprobar si la consulta tiene parámetros nombrados (por ejemplo, :nombre)
        preg_match('/INTO\s+(\w+)\s*\((.*?)\)\s*VALUES\s*\((.*?)\)/i', $sql, $matches);
        if (!$matches) return false;

        $table = $matches[1];
        $columns = array_map('trim', explode(',', $matches[2]));
        $valuesPart = $matches[3];

        // Verifica que los placeholders se asignen correctamente
        preg_match_all('/:\w+/', $valuesPart, $valueMatches);
        $placeholders = $valueMatches[0];
        // Asigna los valores a los placeholders
        $values = [];
        foreach ($placeholders as $placeholder) {
            $realValue = $this->getRealValue($placeholder);
            if ($realValue === null) {
                echo "[DEBUG] No se encontró valor para: $placeholder\n";
            }
            $values[] = $realValue;
        }

        // Comprobar si los valores son parámetros nombrados
        if (strpos($valuesPart, ':') !== false) {
            // Extraer los parámetros nombrados de la cadena de valores
            preg_match_all('/:\w+/', $valuesPart, $valueMatches);
            $placeholders = $valueMatches[0];

            // Aquí asignamos los valores reales a los placeholders
            $values = [];
            foreach ($placeholders as $placeholder) {
                $realValue = $this->getRealValue($placeholder); // Definir esta función según la lógica de tu aplicación
                $values[] = $realValue;
            }
        } else {
            // Si no hay parámetros nombrados, entonces son valores directos
            $values = array_map(function($v) {
                return trim(trim($v), "'\"");
            }, explode(',', $valuesPart));
        }

        if (!isset($this->data['data'][$table]['rows'])) return false;

        $colsDef = $this->data['data'][$table]['columns'] ?? [];

        // Buscar si hay columna auto_increment y no viene en el INSERT
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

        // Validaciones de tipo, not null y longitud 
        foreach ($columns as $i => $col) {
            $val = $values[$i] ?? null; 
            $colDef = $colsDef[$col] ?? null;

            if (!$colDef) continue;

            // Validar NOT NULL
            if (!empty($colDef['not_null']) && ($val === null || $val === '')) {
                throw new Exception("[ERROR] El campo '$col' no puede ser NULL.");
            }

            // Validar tipo de dato (solo 'int' y 'string' por ahora)
            if ($colDef['type'] === 'int' && !is_numeric($val)) {
                throw new Exception("[ERROR] El valor de '$col' debe ser un número entero.");
            }

            if (empty($colDef['auto_increment']) && isset($val) && $val !== null && strlen($val) > $colDef['length']) {
                throw new Exception("[ERROR] El valor de '$col' excede el tamaño máximo de {$colDef['length']} caracteres.");
            }
            
        }

        // Aquí ahora correctamente se combinan las columnas con los valores
        $newRow = array_combine($columns, $values);

        // Agregamos la nueva fila a la tabla
        $this->data['data'][$table]['rows'][] = $newRow;

        // Asignamos el ultimo valor agregado para obtener con lastInsertId
        $this->lastInsertId = $lastId + 1;

        // Guardamos el JSON actualizado en el archivo
        file_put_contents($this->file, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }

    public function lastInsertId() {
        return $this->lastInsertId;
    }

    public function bindValue($key, $value) {
        $this->boundParams[$key] = $value;
    }

    private function getRealValue($placeholder) {
        return $this->boundParams[$placeholder] ?? null;
    }

    public function handleUpdate($sql) {
        preg_match('/UPDATE\s+(\w+)\s+SET\s+(.*?)\s+WHERE\s+(.*)/i', $sql, $matches);
        if (!$matches) return false;

        [$_, $table, $setClause, $whereClause] = $matches;

        if (!isset($this->data['data'][$table]['rows'])) return false;

        parse_str(str_replace(",", "&", str_replace("=", "=", $setClause)), $setValues);

        // Normalizar 
        $set = [];
        foreach (explode(',', $setClause) as $pair) {
            [$k, $v] = explode('=', $pair);
            //$set[trim($k)] = trim(trim($v), "' ");
            $set[trim($k)] = trim($v, " '\"");
        }

        // Muy básica: solo admite condiciones como columna='valor'
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


    public function prepare($sql) {
        return new JESTORStatement($this, $sql);
    }
}

if (!defined('MYSQLI_ASSOC')) define('MYSQLI_ASSOC', 1);
if (!defined('MYSQLI_NUM')) define('MYSQLI_NUM', 2);
if (!defined('MYSQLI_BOTH')) define('MYSQLI_BOTH', 3);

class JESTORResult {
    private $rows;
    private $index = 0;

    public function __construct($rows) {
        $this->rows = $rows;
    }
    
    public function fetch() {
        // Aseguramos que el índice no supere el número de filas
        if ($this->index < count($this->rows)) {
            return $this->rows[$this->index++];
        }
        return null;  // Si no hay más filas, retornamos null
    }
    
    // Método para restablecer el índice
    public function resetIndex() {
        $this->index = 0;
    }
    
    // Devuelve la siguiente fila como un array asociativo (o NULL si ya no hay más filas)
    public function fetch_assoc() {
        if ($this->index < count($this->rows)) {
            return $this->rows[$this->index++];
        }
        return null;
    }

    // Devuelve todas las filas sin modificarlas
    public function all() {
        return $this->rows;
    }

    // Obtiene todas las filas en el modo especificado (default es MYSQLI_ASSOC)
    public function fetchAll($mode = MYSQLI_ASSOC) {
        return $this->fetch_all($mode);
    }

    // Devuelve todas las filas según el modo: MYSQLI_ASSOC, MYSQLI_NUM, o MYSQLI_BOTH
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

    // Devuelve el número de filas
    public function num_rows() {
        return count($this->rows);
    }
}

class JESTORStatement {
    private $jestor;
    private $sql;
    private $params = [];

    public function __construct($jestor, $sql) {
        $this->jestor = $jestor;
        $this->sql = $sql;
    }

    public function execute($params = []) {
        $sql = $this->sql;
        foreach ($params as $key => $value) {
            // Escapar comillas simples en los valores
            $escaped = str_replace("'", "\\'", $value);
            $sql = str_replace($key, "'$escaped'", $sql);
        }

        return $this->jestor->query($sql);
    }

    public function bindValue($key, $value) {
        $this->params[$key] = $value;
    }

    public function fetch() {
        // No implementado, pero podría devolver resultados si fuera SELECT
        return null;
    }
}


?>
