<?php
namespace web\pages;

use app\PvDataExtractor;
use app\PvModelBuilderDisplay;
use app\PvModelBuilderPegaseEdition;
use nulib\os\path;
use nulib\os\sh;
use nulib\web\params\F;
use nur\v\al;
use nur\v\page;
use nur\v\v;
use nur\v\vo;
use web\init\ANavigablePage;

class TbinDumpExtractPage extends ANavigablePage {
  function setup() {
    al::reset();
    $input = F::get("input");
    if ($input) {
      $input = path::join(__DIR__.'/../../tbin/samples', basename($input));
      if (!file_exists($input)) {
        al::error("input does not exist");
      } else {
        $extractor = new PvDataExtractor();
        $pvData = $extractor->extract($input);
        switch (F::get("type")) {
        case "d":
          $display = new PvModelBuilderDisplay($pvData);
          $display->compute();
          $data = $pvData->ws;
          break;
        case "p":
          $pegase = new PvModelBuilderPegaseEdition($pvData);
          $pegase->compute();
          $data = $pvData->ws;
          break;
        default:
          $data = $pvData->data;
          break;
        }
        $this->addPlugin($this->cdumpData = new CDumpData($data));
      }
    }
  }

  protected ?CDumpData $cdumpData = null;

  function print(): void {
    al::print();
    if ($this->cdumpData !== null) {
      vo::p([
        v::a("RESET", page::self()),
        ", ",
        v::a("pvData", page::bu("", F::select(null, ["type"]))),
        ", ",
        v::a("display", page::bu("", F::select(null, null, [
          "type" => "d",
        ]))),
        ", ",
        v::a("pegase", page::bu("", F::select(null, null, [
          "type" => "p",
        ]))),
      ]);
      $this->cdumpData->print();
    } else {
      vo::sdiv(["class" => "list-group"]);
      $inputs = sh::ls_files(__DIR__."/../../tbin/samples", "*.csv");
      foreach ($inputs as $input) {
        vo::a([
          "class" => "list-group-item",
          "href" => page::bu("", ["input" => $input]),
          $input
        ]);
      }
      vo::ediv();
    }
  }
}
