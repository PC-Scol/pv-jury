<?php
namespace web\pages;

use app\CsvPvModel1Builder;
use app\PvChannel;
use app\pvs;
use Exception;
use nulib\cl;
use nulib\os\path;
use nulib\text\words;
use nur\authz;
use nur\b\authnz\IAuthzUser;
use nur\v\al;
use nur\v\bs3\fo\Form;
use nur\v\bs3\fo\FormBasic;
use nur\v\icon;
use nur\v\page;
use nur\v\plugins\showmorePlugin;
use nur\v\v;
use nur\v\vo;
use web\init\APvPage;

class ConvertPage extends APvPage {
  const TITLE = "PV Jury";

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

    $objs = [];
    foreach ($pvData->gptObjs as $igpt => $gpt) {
      $gptTitle = $gpt["title"];
      if ($gptTitle !== null) $objs["$igpt"] = $gptTitle;
      foreach ($gpt["objs"] as $iobj => $obj) {
        if ($igpt !== 0 || $iobj !== 0) {
          $objs["$igpt.$iobj"] = $obj;
        }
      }
    }
    $this->objs = $objs;

    $builder = $this->builder = new CsvPvModel1Builder($pvData);
    $sessions = $this->sessions = $builder->getSessions();
    $convertfo = $this->convertfo = new FormBasic([
      "method" => "post",
      "schema" => [
        "ises" => ["?int", null, "Session"],
        "order" => ["string", null, "Ordre"],
        "xc" => ["bool", null, "NE PAS inclure les controles"],
        "xe" => ["bool", null, "Exclure les objets pour lesquels il n'y a ni note ni résultat"],
        "objs" => ["array", [], "Objets à inclure dans l'édition"],
      ],
      "params" => [
        "convert" => ["control" => "hidden", "value" => 1],
        "ises" => cl::merge([
          "control" => "select",
          "items" => $sessions,
        ], count($sessions) > 1? [
          "no_item_value" => "",
          "no_item_text" => "-- Veuillez choisir la session --",
        ]: null),
        "xc" => [
          "control" => "hidden",
          "value" => false,
          //"control" => "checkbox",
          //"value" => 1,
        ],
        "order" => [
          "control" => "select",
          "items" => [
            [CsvPvModel1Builder::ORDER_MERITE, "Classer par mérite (note)"],
            [CsvPvModel1Builder::ORDER_ALPHA, "Classer par ordre alphabétique (nom)"],
            [CsvPvModel1Builder::ORDER_CODAPR, "Classer par numéro apprenant"],
          ],
        ],
        "xe" => [
          "control" => "checkbox",
          "value" => 1,
        ],
        "objs" => false,
      ],
      "submit" => [
        "Editer le PV",
        "name" => "action",
        "value" => "convert",
        "accesskey" => "s",
        "class" => "btn-primary"
      ],
      "submitted_key" => "convert",
      "autoload_params" => true,
    ]);

    if ($convertfo->isSubmitted()) {
      al::reset();
      if ($convertfo["ises"] === null) {
        al::error("Vous devez choisir la session");
        $this->dispatchAction(false);
      }
    }

    $this->sm = $this->addPlugin(new showmorePlugin());
  }

  private array $pv;

  private ?array $objs;

  private PvChannel $pvChannel;

  private bool $canDelete;

  private int $count;

  private array $sessions;

  private CsvPvModel1Builder $builder;

  private Form $convertfo;

  private Form $deletefo;

  private showmorePlugin $sm;

  const VALID_ACTIONS = ["download", "convert", "delete"];
  const ACTION_PARAM = "action";

  function convertAction() {
    page::more_time();
    $builder = $this->builder;
    $convertfo = $this->convertfo;
    $builder->setIses($convertfo["ises"]);
    $builder->setOrder($convertfo["order"]);
    $builder->setExcludeControles(boolval($convertfo["xc"]));
    $builder->setExcludeUnlessHaveValue(boolval($convertfo["xe"]));
    $builder->setIncludeObjs($convertfo["objs"] ?? []);
    $suffix = $builder->getSuffix();
    $output = path::filename($this->pvData->origname);
    $output = path::ensure_ext($output, "-$suffix.xlsx", ".csv");
    try {
      $builder->build($output)->send();
    } catch (Exception $e) {
      al::error($e->getMessage());
    }
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

      vo::p(["<b>Edition du PV</b> (met en forme les données du fichier CSV pour impression. inclure aussi les statistiques)"]);
      if ($pvData->ws["resultats"] === null) {
        vo::p([
          "class" => "alert alert-warning",
          "Pour information, le fichier ne contient pas de résultats sur l'objet délibéré. L'encart 'nb étudiants/admis/ajournés' sera vide lors de l'édition"
        ]);
      }
      al::print();
      $convertfo = $this->convertfo;
      $convertfo->autoloadParams();
      $convertfo->printAlert();
      $convertfo->printStart();
      $convertfo->printControl("convert");
      $convertfo->printControl("ises");
      $convertfo->printControl("xc");
      $convertfo->printControl("order");

      $sm = $this->sm;
      $sm->printStartc();
      vo::p([
        "<em>Exclusion d'objets maquettes</em> : ",
        "vous pouvez exclure certains objets de l'édition du PV. ",
        $sm->invite("Afficher la liste des objets maquettes..."),
      ]);
      $sm->printStartp();
      $convertfo->printControl("xe");
      vo::sdiv(["class" => "form-group"]);
      foreach ($this->objs as $iobj => $obj) {
        if (str_contains($iobj, ".")) {
          $convertfo->printCheckbox("Inclure $obj", "objs[]", $iobj, true, [
            "naked" => true,
            "naked_label" => true,
          ]);
        } else {
          vo::p(v::b($obj));
        }
      }
      vo::ediv();
      $sm->printEnd();

      $convertfo->printControl("");
      $convertfo->printEnd();

      vo::p([
        "<b>Gestion de l'import</b>. Le fichier original était nommé <code>{$pvData->origname}</code>",
      ]);

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
    }

    if ($this->canDelete) {
      if ($valid) {
        vo::p([
          "<b>Suppression de l'import</b>. Si ce fichier a été importé par erreur, vous pouvez le supprimer",
        ]);
      } else {
        vo::p([
          "<b>Gestion de l'import</b>. Le fichier original était nommé <code>",
          $this->pv["origname"],
          "</code>",
        ]);
        vo::p([
          "class" => "alert alert-danger",
          "Ce fichier est invalide",
        ]);
      }
      $this->deletefo->print();
    }

    if ($valid) {
      $builder = $this->builder;
      $builder->setExcludeUnlessHaveValue(true);
      foreach ($this->sessions as [$ises, $session]) {
        vo::h2($session);
        $builder->setIses($ises);
        $builder->compute();
        $builder->print();
      }
    }
  }
}
