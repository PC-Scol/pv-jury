<?php
namespace web\pages;

use app\PvModelBuilder;
use app\PvModelBuilderClassicEdition;
use app\PvChannel;
use app\PvModelBuilderPegaseEdition;
use app\pvs;
use Exception;
use nulib\cl;
use nulib\os\path;
use nulib\StateException;
use nulib\text\words;
use nur\authz;
use nur\b\authnz\IAuthzUser;
use nur\session;
use nur\v\al;
use nur\v\bs3\fo\Form;
use nur\v\bs3\fo\FormBasic;
use nur\v\bs3\fo\FormInline;
use nur\v\icon;
use nur\v\page;
use nur\v\plugins\autosubmitSelectPlugin;
use nur\v\plugins\showmorePlugin;
use nur\v\v;
use nur\v\vo;
use web\init\APvPage;

class ConvertPage extends APvPage {
  const TITLE = "PV Jury";
  const PLUGINS = [
    showmorePlugin::class,
  ];

  function setup(): void {
    parent::setup();

    $user = authz::get();
    $pvChannel = $this->pvChannel = pvs::channel();
    $this->pv = $pv = $pvChannel->one(["name" => $this->name]);
    $this->canDelete = $pv !== null && ($pv["cod_usr"] === $user->getUsername() || $user->isPerm("*"));

    $deletefo = $this->deletefo = new FormBasic([
      "method" => "post",
      "params" => [
        "delete" => ["control" => "hidden", "value" => 1],
      ],
      "submit" => [
        "Supprimer cet import",
        "name" => "action",
        "value" => "delete",
        "class" => "btn-danger"
      ],
      "submitted_key" => "delete",
      "autoload_params" => true,
    ]);
    if ($this->pvError) return;

    $pvData = $this->pvData;
    $this->count = count($pvData->rows ?? []);

    $modele = session::get("modele", 1);
    $modelefo = $this->modelefo = new FormInline([
      "method" => "post",
      "params" => [
        "set_modele" => ["control" => "hidden", "value" => 1],
        "modele" => [
          "control" => "select",
          "label" => "Modèle d'édition",
          "items" => [
            [1, "Modèle classique"],
            [2, "Modèle classique avec coefficients"],
            [3, "Modèle PEGASE en colonnes"],
          ],
          "default" => $modele,
        ],
      ],
      "autoadd_submit" => false,
      "submitted_key" => "set_modele",
      "autoload_params" => true,
    ]);
    if ($modelefo->isSubmitted()) {
      $modele = $modelefo["modele"];
      session::set("modele", $modele);
    }
    $this->addPlugin(new autosubmitSelectPlugin("#modele"));
    $this->modele = $modele;

    switch ($modele) {
    case 1:
    case 2:
      $builder = new PvModelBuilderClassicEdition($pvData);
      break;
    case 3:
      $builder = new PvModelBuilderPegaseEdition($pvData);
      break;
    default:
      throw StateException::unexpected_state();
    }
    $this->builder = $builder;
    if (!$builder->checkForm()) $this->dispatchAction(false);
  }

  private array $pv;

  private PvChannel $pvChannel;

  private bool $canDelete;

  private int $count;

  private Form $modelefo;

  private int $modele;

  private PvModelBuilder $builder;

  private Form $deletefo;

  const VALID_ACTIONS = ["download", "convert", "delete"];
  const ACTION_PARAM = "action";

  function convertAction() {
    page::more_time();
    $this->builder->doFormAction([
      "add_coeff_col" => $this->modele == 2,
    ]);
    page::redirect(true);
  }

  function deleteAction() {
    if ($this->canDelete) {
      $pvChannel = $this->pvChannel;
      $pvChannel->delete(["name" => $this->name]);
    }
    page::redirect(IndexPage::class);
  }

  const HAVE_JQUERY = true;

  function printJquery(): void {
    ?>
<script type="text/javascript">
  jQuery.noConflict()(function($) {
    $("#copy-link").click(function() {
      var $this = $(this);
      navigator.clipboard.writeText($("#view-link").attr("href"));
      $this.addClass("btn-success").text("Lien copié!");
      window.setTimeout(function() {
        $this.removeClass("btn-success").text("Copier le lien");
      }, 1000);
      return false;
    });
  });
</script>
<?php
  }
  function print(): void {
    $valid = !$this->pvError;
    if ($valid) {
      $pvData = $this->pvData;
      $title = array_filter($pvData->title);
      vo::h1([
        $title[0],
        "<br/>",
        v::tag("small", [
          join("<br/>", array_slice($title, 1)),
        ])
      ]);

      vo::start("fieldset");
      vo::tag("legend", "Edition du PV");
      vo::p(["Mettre en forme les données du fichier CSV pour impression. inclure aussi les statistiques"]);
      $this->modelefo->print();
      al::print();
      $this->builder->printForm();
      vo::end("fieldset");

      vo::start("fieldset");
      vo::tag("legend", "Gestion de l'import");
      vo::p(["Le fichier original était nommé <code>{$pvData->origname}</code>"]);

      vo::ul([
        v::li([
          "Il y a ",
          words::q($this->count, "l'étudiant#s dans ce fichier|m"),
        ]),
        v::li([
          v::a([
            "href" => page::bu("", [
              "action" => "download",
              "n" => $this->name,
            ]),
            "Télécharger ",
            icon::download("au format Excel"),
          ]),
          " (convertir le fichier CSV au format Excel <em>sans modifications du contenu</em>)",
          v::if(authz::get()->isPerm("*"), [
            ", ou ",
            v::a([
              "href" => page::bu("", [
                "action" => "download",
                "n" => $this->name,
                "format" => "csv",
              ]),
              "télécharger ",
              icon::download("le fichier original"),
            ]),
          ]),
        ]),
        v::li([
          v::a([
            "id" => "view-link",
            "href" => getenv("BASE_URL").page::bu(ViewPage::class, [
                "n" => $this->name,
              ]),
            "target" => "_blank",
            "Afficher ",
            icon::eye_open("le détail des dossiers étudiants"),
          ]),
          " (consultation en ligne des résultats, par étudiant)",
          "<br/>",
          v::a([
            "id" => "copy-link",
            "class" => "btn btn-default",
            "href" => "#",
            "Copier le lien"
          ]),
          " (pour partager avec les enseignants)",
        ]),
      ]);
      vo::end("fieldset");
    }

    if ($this->canDelete) {
      vo::start("fieldset");
      if ($valid) {
        vo::tag("legend", "Suppression de l'import");
        vo::p(["Si ce fichier a été importé par erreur, vous pouvez le supprimer"]);
      } else {
        vo::tag("legend", "Gestion de l'import");
        vo::p([
          "class" => "alert alert-danger",
          "Ce fichier est invalide",
        ]);
        vo::p(["Le fichier original était nommé <code>{$this->pv["origname"]}</code>"]);
      }
      $this->deletefo->print();
      vo::end("fieldset");
    }

    if ($valid) {
      $this->builder->print([
        "add_coeff_col" => $this->modele == 2,
      ]);
    }
  }
}
