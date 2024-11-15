<?php
namespace app;

/**
 * Class Model1CsvBuilder: construire un document pouvant servir à faire une
 * délibération: sélection par session, et affichage en colonnes
 */
class CsvPvModel1Builder extends CsvPvBuilder {
  function __construct(string $session) {
    $this->session = $session;
  }

  private string $session;

  protected function compute(array &$pvData): void {

  }

  protected function writeRows(array $pvData): void {

  }
}
