<?php
	namespace Seagull\ORM;

	use Exception, PDO;

	class Core {
		private static $pdo;
		private static $classname;

		/*
		 * Static methods, used for cache-control and loading
		 */
		public static function load($pdo, $force_cache = false) {
			if($pdo instanceof PDO)
				Core::$pdo = $pdo;
			else
				throw new Exception("Must pass a valid PDO object to Seagull_ORM.");

			// Creates cache files upon request ($force_cache) or if they're not present
			if($force_cache || !file_exists('Seagull_ORM.cache.php'))
				Core::create_cache();

			require_once 'Seagull_ORM.cache.php';
		}

		public static function create_cache() {
			$pdo = &Core::$pdo;

			// Getting tables from database
			$pdo_result = $pdo->query('SHOW TABLES');

			while($table = $pdo_result->fetch(PDO::FETCH_NUM))
				$tables[] = ucwords($table[0]);

			// Getting columns for each table
			foreach($tables as $table) {
				$pdo_result = $pdo->query("SHOW COLUMNS FROM {$table};");

				while($column = $pdo_result->fetch()) {
					$columns[$table][] = $column['Field'];
				}
			}

			// Common cache file header
			$classes = "namespace Seagull\ORM;";
			$classes .= "require_once 'Seagull_ORM.php';";

			// Loop to create the classes for each table
			foreach($tables as $table) {
				$classes .= "Class {$table} extends Core {";

				foreach($columns[$table] as $column) {
					$classes .= "public \${$column};";
				}

				$classes .= "}";
			}

			// Create the cache file
			$file = getcwd() . DIRECTORY_SEPARATOR . 'Seagull_ORM.cache.php';
			$file_handle = fopen($file, 'w');
			fwrite($file_handle, '<?php ' . $classes . ' ?>');
			fclose($file_handle);
		}

		/*
		 * Non-static methods, used by the table classes
		 */

		 // Prevents dynamic property creation
		 public function __set($name, $value) {
			if( property_exists($this, $name))
				$this->$name = $value;
			else
				throw new Exception("[Seagull ORM]: Invalid property (" . $name . ")");
		}

		// Class constructor fetches all data using column and value provided
		public function __construct($column = null, $value = null) {
			$class = explode('\\', get_class($this));
			Core::$classname = array_pop($class);

			if($column && $value) {
				$pdo = &Core::$pdo;

				$pdo_statement = $pdo->prepare("SELECT * FROM " . Core::$classname . " WHERE {$column} = ?;");
				$pdo_statement->bindValue(1, $value);
				$pdo_statement->execute();

				$pdo_result = $pdo_statement->fetch(PDO::FETCH_ASSOC);

				foreach($pdo_result as $column => $value) {
					$this->$column = $value;
				}
			}
		}

		// Saves change to table
		public function save() {
			$pdo = &Core::$pdo;

			$columns = get_object_vars($this);
			foreach($columns as $column => $value) {
				if(!$value) {
					unset($columns[$column]);
				}
			}

			$sql_columns = implode(', ', array_keys($columns));
			$sql_parameters = ':' . implode(', :', array_keys($columns));
			$pdo_statement = $pdo->prepare("REPLACE INTO " . Core::$classname . " ({$sql_columns}) VALUES ({$sql_parameters});");
			foreach($columns as $column => $value) {
				$pdo_statement->bindValue(":{$column}", $value);
			}

			return $pdo_statement->execute() ? true : false;
		}

	}
