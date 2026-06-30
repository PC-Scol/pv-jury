<?php
namespace web\pages;

use app\PvDataExtractor;
use nulib\os\path;
use nulib\web\params\F;
use nur\v\al;
use web\init\ANavigablePage;

class TbinDumpExtractPage extends ANavigablePage {
  function setup() {
    al::reset();
    $input = F::get("input");
    if ($input === null) {
      al::error("input is null");
    } else {
      $input = path::join(__DIR__.'/../../tbin/samples', basename($input));
      if (!file_exists($input)) {
        al::error("input does not exist");
      } else {
        $extractor = new PvDataExtractor();
        $pvData = $extractor->extract($input);
        $this->addPlugin($this->cdumpData = new CDumpData($pvData->data));
      }
    }
  }

  protected ?CDumpData $cdumpData = null;

  function print(): void {
    al::print();
    if ($this->cdumpData !== null) {
      $this->cdumpData->print();
    }
  }
}
