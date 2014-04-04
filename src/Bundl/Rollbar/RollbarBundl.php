<?php
/**
 * Created by PhpStorm.
 * User: tom.kay
 * Date: 03/04/2014
 * Time: 16:09
 */

namespace Bundl\Rollbar;

use Bundl\Sidekix\SidekixBundl;
use Cubex\Bundle\Bundle;
use Cubex\Events\EventManager;
use Cubex\Events\IEvent;
use Cubex\Foundation\Config\Config;
use Cubex\Foundation\Container;
use Cubex\Log\Log;
use Psr\Log\LogLevel;

class RollbarBundl extends Bundle
{
  protected $_logLevel;

  public function init($initialiser = null)
  {
    $config          = Container::config()->get("rollbar", new Config());
    $token           = $config->getStr("post_server_item", null);
    $this->_logLevel = $config->getStr("log_level", LogLevel::WARNING);

    $config = [
      'access_token' => $token,
      'environment'  => CUBEX_ENV,
      'root'         => CUBEX_PROJECT_ROOT
    ];

    if(class_exists('\Bundl\Sidekix\SidekixBundl'))
    {
      $info = SidekixBundl::getDiffuseVersionInfo();
      if($info && isset($info['version']))
      {
        $config['code_version'] = $info['version'];
      }
    }
    // installs global error and exception handlers
    \Rollbar::init($config);

    EventManager::listen(
      EventManager::CUBEX_LOG,
      [$this, "log"]
    );
  }

  public function log(IEvent $e)
  {
    $level = $e->getStr('level');
    if(Log::logLevelAllowed($level, $this->_logLevel))
    {
      // "critical", "error", "warning", "info", "debug"
      switch($level)
      {
        case LogLevel::EMERGENCY:
        case LogLevel::ALERT:
        case LogLevel::CRITICAL:
          $level = 'critical';
          break;
        case LogLevel::ERROR:
          $level = 'error';
          break;
        case LogLevel::WARNING:
          $level = 'warning';
          break;
        case LogLevel::DEBUG:
          $level = 'debug';
          break;
        case LogLevel::NOTICE:
        case LogLevel::INFO:
        default:
          $level = 'info';
          break;
      }
      $data = [
        'file'    => $e->getStr('file'),
        'line'    => $e->getStr('line'),
        'context' => $e->getArr('context'),
      ];
      if(CUBEX_CLI)
      {
        $data['cli_command'] = implode(' ', $_SERVER['argv']);
      }
      \Rollbar::report_message($e->getStr('message'), $level, $data);
    }
  }
}
