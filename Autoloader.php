<?php
namespace Jamm\Autoload;

/**
 * This class should be placed in /home/.../vendors/Jamm/Autoload/ directory
 */
class Autoloader
{
	protected static $classes = array();
	protected static $modules_dir;
	protected static $functions = array();
	protected static $started = false;
	protected static $namespaces_dirs = array();

	/**
	 * @param string $class_name
	 * @param string $path
	 * @return bool
	 */
	public static function Register_Class($class_name, $path)
	{
		$class_name = strtolower($class_name);
		if ($path[0]!='/') $path = self::get_modules_dir().'/'.$path;

		self::$classes[$class_name] = $path;
	}

	/**
	 * @param string $class_name
	 * @return bool
	 */
	public static function autoload($class_name)
	{
		if ($class_name[0]=='\\') $class_name = substr($class_name, 1);

		$file = self::find_in_classes($class_name);
		if (empty($file)) $file = self::find_in_namespaces($class_name);

		if (!empty($file))
		{
			/** @noinspection PhpIncludeInspection */
			include $file;
			if (!class_exists($class_name, false) && !interface_exists($class_name, false))
			{
				trigger_error('Class '.$class_name.' was not declared in included file: '.$file.PHP_EOL.self::current_backtrace(), E_USER_WARNING);
				return false;
			}
			return true;
		}

		$bt = self::current_backtrace();
		if (strpos($bt, 'class_exists')===false) trigger_error('Class '.$class_name.' was not found. Trace: '.$bt, E_USER_WARNING);
		return false;
	}

	public static function find_in_classes($class_name)
	{
		$class_name = strtolower($class_name);

		if (!empty(self::$classes[$class_name])) return self::$classes[$class_name];
		return false;
	}

	public static function find_in_namespaces($class)
	{
		if (empty(self::$namespaces_dirs)) return false;
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

		foreach (self::$namespaces_dirs as $ns => $dir)
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

	/** @return bool */
	public static function Start()
	{
		if (self::$started) return true;
		self::$started = true;
		$home = explode(DIRECTORY_SEPARATOR, __DIR__);
		$home = DIRECTORY_SEPARATOR.$home[1].DIRECTORY_SEPARATOR.$home[2];
		define('HOME_DIR', $home, true);
		self::RegisterCommon();
		return spl_autoload_register(array(__CLASS__, 'autoload'));
	}

	public static function RegisterCommon()
	{
		self::register_namespace_dir('', self::get_modules_dir());
	}

	/**
	 * Return modules dir like "/home/dir/modules"
	 * @return string
	 */
	public static function get_modules_dir()
	{
		if (empty(self::$modules_dir)) self::set_modules_dir(__DIR__.'/../../');
		return self::$modules_dir;
	}

	public static function set_modules_dir($dir)
	{
		$dir = realpath($dir);
		if (!empty($dir) && is_dir($dir)) self::$modules_dir = $dir;
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
	public static function register_namespace_dir($namespace, $dir)
	{
		if (($dir = realpath($dir))===false)
		{
			trigger_error('Namespace was not registered! Directory not found');
			return false;
		}
		if (strpos($namespace, '\\')!==false) $namespace = trim($namespace, '\\').'\\';
		self::$namespaces_dirs[$namespace] = $dir.'/';
	}

	private static function current_backtrace()
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

Autoloader::Start();

