#!/usr/bin/php
<?php
namespace Kloudspeaker;

require 'api/vendor/auto/autoload.php';
require 'api/system.php';
require 'api/Kloudspeaker/Utils.php';

$systemInfo = getKloudspeakerSystemInfo();

$logger = new \Monolog\Logger('kloudspeaker-cli');
$logLevel = (isset($systemInfo["config"]["debug"]) and $systemInfo["config"]["debug"]) ? \Monolog\Logger::DEBUG : \Monolog\Logger::INFO;
$logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', $logLevel));
$logger->pushHandler(new \Monolog\Handler\StreamHandler("cli.log", $logLevel));

function ln() {
	global $logger;

	foreach (func_get_args() as $s) {
		if (is_array($s))
			$logger->info(Utils::array2str($s));
		else
			$logger->info($s);
	}
}

class ErrorHandler {
	public function php($errno, $errstr, $errfile, $errline, $errCtx) {
		ln("PHP error #" . $errno . ", " . $errstr . " (" . $errfile . ":" . $errline . ")");
		die();
	}

	public function exception($exception) {
	    if (is_a($exception, "Kloudspeaker\KloudspeakerException")) {
	    	ln("Kloudspeaker exception");
	        ln(["code" => $exception->getErrorCode(), "msg" => $exception->getMessage(), "result" => $exception->getResult(), "trace" => $exception->getTraceAsString()]);
	    } else if (is_a($exception, "ServiceException")) {
	        //legacy
	        ln("Kloudspeaker exception");
	        ln(["code" => $exception->getErrorCode(), "msg" => $exception->type() . "/" . $exception->getMessage(), "result" => $exception->getResult(), "trace" => $exception->getTraceAsString()]);
	    } else {
	    	ln("Unknown exception: ".$exception->getMessage());
	        ln(["msg" => $exception->getMessage(), "trace" => $exception->getTraceAsString()]);
	    }
		die();
	}

	public function fatal() {
		$error = error_get_last();
		//var_dump($error);
		if ($error !== NULL) ln("FATAL ERROR", $error);
	}
}

$errorHandler = new ErrorHandler();
set_error_handler(array($errorHandler, 'php'));
set_exception_handler(array($errorHandler, 'exception'));
register_shutdown_function(array($errorHandler, 'fatal'));

ln("Kloudspeaker CLI", ["version" => $systemInfo["version"], "revision" => $systemInfo["revision"]]);

set_include_path($systemInfo["root"].DIRECTORY_SEPARATOR.'api' . PATH_SEPARATOR . get_include_path());

require 'autoload.php';
require 'setup/Installer.php';

$config = new Configuration($systemInfo);

$app = new Api($config);
$app->initialize(new \KloudspeakerLegacy($config), [ "logger" => function() use ($logger) {
    return $logger;
}]);
$container = $app->getContainer();

$container['logger'] ;
$logger = $container->logger;

$installer = new \Kloudspeaker\Setup\Installer($systemInfo, $container);
$installer->initialize();

$opts = getOpts($argv);
if (count($opts["commands"]) === 0) {
	ln("No options specified");
	exit(0);
}

$command = $opts["commands"][0];
$options = $opts["options"];

ln($opts);

if ("list" == $command) {
	ln($container->commands->get(count($opts["commands"]) > 1 ? $opts["commands"][1] : NULL));
	exit(0);
}

if (!$container->commands->exists($command)) {
	ln("Command not found [$command]");
	exit(0);
}

ln("Command [$command]");
$result = $container->commands->execute($command, $options);

ln("Result:", $result);

// TOOLS

function getOpts($args) {
	array_shift($args);
	$endofoptions = false;

	$ret = array(
		'commands' => array(),
		'options' => array(),
		'flags' => array(),
		'arguments' => array(),
	);

	while ($arg = array_shift($args)) {
		// if we have reached end of options,
		//we cast all remaining argvs as arguments
		if ($endofoptions) {
			$ret['arguments'][] = $arg;
			continue;
		}

		// Is it a command? (prefixed with --)
		if (substr($arg, 0, 2) === '--') {
			// is it the end of options flag?
			if (!isset($arg[3])) {
				$endofoptions = true; // end of options;
				continue;
			}

			$value = "";
			$com = substr($arg, 2);

			// is it the syntax '--option=argument'?
			if (strpos($com, '=')) {
				list($com, $value) = explode("=", $com, 2);
			}

			// is the option not followed by another option but by arguments
			elseif (strpos($args[0], '-') !== 0) {
				while (strpos($args[0], '-') !== 0) {
					$value .= array_shift($args) . ' ';
				}

				$value = rtrim($value, ' ');
			}

			$ret['options'][$com] = !empty($value) ? $value : true;
			continue;
		}

		// Is it a flag or a serial of flags? (prefixed with -)
		if (substr($arg, 0, 1) === '-') {
			for ($i = 1;isset($arg[$i]); $i++) {
				$ret['flags'][] = $arg[$i];
			}

			continue;
		}

		// finally, it is not option, nor flag, nor argument
		$ret['commands'][] = $arg;
		continue;
	}

	/*if (!count($ret['options']) && !count($ret['flags'])) {
		$ret['arguments'] = array_merge($ret['commands'], $ret['arguments']);
		$ret['commands'] = array();
	}*/
	\Logging::debug("=>" . Utils::array2str($ret));
	return $ret;
}

exit(0);