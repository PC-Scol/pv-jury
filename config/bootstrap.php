<?php
namespace app\config;

use nulib\app\app;
use nulib\app\config as nconfig;
use nulib\app\config\EnvConfig as nEnvConfig;
use nulib\app\web\Application;
use nulib\os\env;
use nulib\output\log as nlog;
use nulib\output\msg as nmsg;
use nulib\output\std\LogMessenger;
use nulib\ref\ref_profiles;
use nur\authz;
use nur\b\authnz\CasAuthzManager;
use nur\b\authnz\ExtAuthzManager;
use nur\config;
use nur\config\ArrayConfig;
use nur\config\EnvConfig;
use nur\msg;
use nur\session;
use nur\v\bs3\Bs3Messenger;
use nur\v\route;
use nur\v\vp\AppCasauthPage;
use nur\v\vp\AppDevauthPage;
use nur\v\vp\AppExtauthPage;
use nur\v\vp\AppHealthcheckPage;
use nur\v\vp\AppLogoutPage;
use web\pages\IndexPage;
use web\pages\LoginPage;

define("AUTH_CAS", env::bool("AUTH_CAS"));
define("AUTH_BASIC", env::bool("AUTH_BASIC"));

class bootstrap {
  const PROJCODE = "pv-jury";

  function configure__initial_config() {
    config::init_appcode(self::PROJCODE);
    config::add(cdefaults::class);
    config::add(new ArrayConfig(["app" => [
      "url" => getenv("BASE_URL"),
    ]]));
    config::add(new EnvConfig());
    config::add(cprod::class, config::PROD);
    config::add(ctest::class, config::TEST);
  }

  function configure__msg() {
    if (config::is_fact(config::FACT_WEB_APP)) {
      msg::set_messenger_class(Bs3Messenger::class, true);
    }
  }

  function configure__authnz() {
    if (\AUTH_CAS) $class = CasAuthzManager::class;
    elseif (\AUTH_BASIC) $class = ExtAuthzManager::class;
    else $class = null;
    if ($class !== null) authz::set_manager_class($class);
  }

  function configure__routes() {
    route::add(["_hk.php", AppHealthcheckPage::class]);
    route::add(["_casauth.php", AppCasauthPage::class]);
    route::add(["_extauth.php", AppExtauthPage::class]);
    route::add(["_devauth.php", AppDevauthPage::class]);
    route::add(["_logout.php", AppLogoutPage::class]);
    route::add(["index.php", IndexPage::class]);
    route::add(["login.php", LoginPage::class]);
    route::add(["", IndexPage::class, route::MODE_PACKAGE]);
  }

  function configure__initial_session() {
    # 4h de session par défaut
    # cf php/conf.d/session.ini si cette valeur est modifiée
    session::set_duration(4 * 60 * 60);
  }
}

\nur_v_bs3::init();
config::init_configurator(new bootstrap());

app::init([
  "projdir" => __DIR__."/..",
  "projcode" => bootstrap::PROJCODE,
  "datadir" => "devel",
]);

nconfig::init_configurator(new class {
  function configure__initial_config() {
    nconfig::add(cdefaults::class);
    nconfig::add(new nEnvConfig());
    nconfig::add(cprod::class, ref_profiles::PROD);
    nconfig::add(ctest::class, ref_profiles::TEST);
  }

  function configure__log() {
    if (app::is_fact(Application::FACT_WEB_APP)) {
      $log = nlog::set_messenger(new LogMessenger([
        "output" => app::get()->getLogfile(),
        "min_level" => nlog::MINOR,
      ]));
      nmsg::set_messenger($log);
    }
  }
});
