<?php
namespace app\config;

use nur\config;
use nur\config\ArrayConfig;
use nur\config\EnvConfig;
use nur\msg;
use nur\sery\app;
use nur\sery\output\log as nlog;
use nur\sery\output\msg as nmsg;
use nur\sery\output\std\StdMessenger;
use nur\session;
use nur\v\bs3\Bs3Messenger;
use nur\v\route;
use nur\v\vp\AppCasauthPage;
use nur\v\vp\AppDevauthPage;
use nur\v\vp\AppHealthcheckPage;
use nur\v\vp\AppLogoutPage;
use web\pages\IndexPage;
use web\pages\LoginPage;

class bootstrap {
  const APPCODE = "pv-jury";

  function configure__initial_config() {
    config::init_appcode(self::APPCODE);
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
      app::init([
        "projdir" => __DIR__."/..",
        "appcode" => self::APPCODE,
        "datadir" => "devel",
      ]);
      msg::set_messenger_class(Bs3Messenger::class, true);
      nmsg::init([
        "log" => new StdMessenger([
          "output" => app::get()->getLogfile(),
          "add_date" => true,
          "min_level" => nlog::MINOR,
        ]),
        "console" => false,
        "say" => false, #XXX implémenter affichage à l'écran
      ]);
    }
  }

  function configure__routes() {
    route::add(["_hk.php", AppHealthcheckPage::class]);
    route::add(["_casauth.php", AppCasauthPage::class]);
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
