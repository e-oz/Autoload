<?php
namespace Jamm\Autoload;

/**
 * Class to organize autoloading by PSR-0 standard
 *
 * A fully-qualified namespace and class must have the following structure \<Vendor Name>\(<Namespace>\)*<Class Name>
 * Each namespace must have a top-level namespace ("Vendor Name").
 * Each namespace can have as many sub-namespaces as it wishes.
 * Each namespace separator is converted to a DIRECTORY_SEPARATOR when loading from the file system.
 * Each "_" character in the CLASS NAME is converted to a DIRECTORY_SEPARATOR. The "_" character has no special meaning in the namespace.
 * The fully-qualified namespace and class is suffixed with ".php" when loading from the file system.
 * Alphabetic characters in vendor names, namespaces, and class names may be of any combination of lower case and upper case.
 * @link http://groups.google.com/group/php-standards/web/psr-0-final-proposal?pli=1
 *
 * This class should be placed in /home/.../vendors/Jamm/Autoload/ directory
 * In case of errors E_USER_WARNING will be triggered
 * Methods of this class doesn't throws exceptions
 *
 * @author OZ <normandiggs@gmail.com>
 * @license http://en.wikipedia.org/wiki/MIT_License MIT
 */
class Autoloader
{
	protected $classes = array();
	protected $modules_dir;
	protected $functions = array();
	protected $namespaces_dirs = array();

	/**
	 * Register class - associate name of the class with path to the file
	 * @param string $class_name
	 * @param string $path
	 * @return bool
	 */
	public function register_class($class_name, $path)
	{
		$class_name = strtolower($class_name);
		if ($path[0]!='/') $path = $this->get_modules_dir().'/'.$path;

		$this->classes[$class_name] = $path;
	}

	/**
	 * @param string $class_name
	 * @return bool
	 */
	public function autoload($class_name)
	{
		if ($class_name[0]=='\\') $class_name = substr($class_name, 1);

		$file = $this->find_in_classes($class_name);
		if (empty($file)) $file = $this->find_in_namespaces($class_name);

		if (!empty($file))
		{
			/** @noinspection PhpIncludeInspection */
			include $file;
			if (!class_exists($class_name, false) && !interface_exists($class_name, false))
			{
				trigger_error('Class '.$class_name.' was not declared in included file: '.$file.PHP_EOL.$this->current_backtrace(), E_USER_WARNING);
				return false;
			}
			return true;
		}

		$bt = $this->current_backtrace();
		if (strpos($bt, 'class_exists')===false) trigger_error('Class '.$class_name.' was not found. Trace: '.$bt, E_USER_WARNING);
		return false;
	}

	/**
	 * Find classname in registered classes
	 * @param string $class_name
	 * @return bool
	 */
	private function find_in_classes($class_name)
	{
		$class_name = strtolower($class_name);

		if (!empty($this->classes[$class_name])) return $this->classes[$class_name];
		return false;
	}

	/**
	 * Find classname in registered namespaces
	 * @param string $class
	 * @return bool|string
	 */
	private function find_in_namespaces($class)
	{
		if (empty($this->namespaces_dirs)) return false;
		$pos = strrpos($class, '\\');
		if ($pos!==false)
		{
			$namespace = substr($class, 0, $pos+1);
			$class_name = str_replace('_', '/', substr($class, $pos+1));
		}
		else
		{
			$namespace = '';
			$class_name = str_replace('_', '/', $class);
		}

		foreach ($this->namespaces_dirs as $ns => $dir)
		{
			if (empty($ns) || stripos($namespace, $ns)===0)
			{
				$class_path = str_replace('\\', '/', substr($namespace, strlen($ns))).$class_name;
				$file = $dir.$class_path.'.php';
				if (file_exists($file)) return $file;
				$file = $dir.$class_path.'.inc';
				if (file_exists($file)) return $file;
				$file = $dir.$class_path.'.class';
				if (file_exists($file)) return $file;
			}
		}
		return false;
	}

	/**
	 * start autoloader (register in spl_autoload), only if wasn't started yet.
	 */
	public function __construct()
	{
		$this->start();
	}

	public function start()
	{
		$home = explode(DIRECTORY_SEPARATOR, __DIR__);
		$home = DIRECTORY_SEPARATOR.$home[1].DIRECTORY_SEPARATOR.$home[2];
		define('HOME_DIR', $home, true);
		$this->register_common();
		spl_autoload_register(array($this, 'autoload'));
	}

	/**
	 * Register the modules directory as root namespace
	 * @return void
	 */
	private function register_common()
	{
		$this->register_namespace_dir('', $this->get_modules_dir());
	}

	/**
	 * Return modules dir like "/home/dir/modules"
	 * By default will be taken directory of this file without two last folders (__DIR__.'/../../')
	 * @return string
	 */
	public function get_modules_dir()
	{
		if (empty($this->modules_dir)) $this->set_modules_dir(__DIR__.'/../../');
		return $this->modules_dir;
	}

	/**
	 * Set directory of modules ("vendors")
	 * @param string $dir
	 * @return void
	 */
	public function set_modules_dir($dir)
	{
		$dir = realpath($dir);
		if (!empty($dir) && is_dir($dir)) $this->modules_dir = $dir;
		else
		{
			trigger_error('Autoloader can not set modules directory: '.$dir);
		}
	}

	/**
	 * Associate namespace with directory
	 * For example, if namespace '\name\space' will be associated with directory '/home/name/space',
	 * class '\name\space\subnamespace\Class_Name.php' will be looked in /home/name/space/subnamespace/Class_Name.php
	 * @param string $namespace name\space\ (last symbol - slash, and no slashes in start)
	 * @param string $dir
	 */
	public function register_namespace_dir($namespace, $dir)
	{
		if (($dir = realpath($dir))===false)
		{
			trigger_error('Namespace was not registered! Directory not found');
			return false;
		}
		if (strpos($namespace, '\\')!==false) $namespace = trim($namespace, '\\').'\\';
		$this->namespaces_dirs[$namespace] = $dir.'/';
	}

	private function current_backtrace()
	{
		$tmp = debug_backtrace();
		if (empty($tmp)) return false;
		$str = '';
		$space = $basespace = '|';
		foreach ($tmp as $t)
		{
			if (!isset($t['file'])) $t['file'] = '[not a file]';
			if (!isset($t['line'])) $t['line'] = '[-1]';
			if ($t['function']=='include' || $t['function']=='include_once' || $t['function']=='current_backtrace' || $t['function']=='write_log') continue;
			$str .= ' '.$space.$t['file']."\t[".$t['line']."]\t";
			if (array_key_exists('class', $t))
			{
				$str .= $t['class'];
				if (isset($t['type'])) $str .= $t['type'];
			}
			$str .= $t['function'];
			$str .= "\n";
			$space .= $basespace;
		}
		return rtrim($str);
	}
}
