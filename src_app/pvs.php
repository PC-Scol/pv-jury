<?php
namespace app;

use nur\sery\app;
use nur\sery\db\Capacitor;
use nur\sery\db\CapacitorStorage;
use nur\sery\db\sqlite\Sqlite;
use nur\sery\db\sqlite\SqliteStorage;
use nur\sery\os\path;
use nur\sery\php\time\DateTime;

class pvs {
  static function basename(string $filename, ?string $ext=null): string {
    $basename = path::basename($filename);
    $basename = preg_replace('/^pv-de-jury-/', "", $basename);
    $basename = preg_replace('/\s+.*$/', "", $basename);
    $basename = preg_replace('/-\d{8}-\d{6}$/', "", $basename);
    return "$basename$ext";
  }

  static function get_date(?string $filename): ?DateTime {
    if ($filename === null) return null;
    $basename = path::basename($filename);
    $basename = preg_replace('/\s+.*$/', "", $basename);
    if (!preg_match('/-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})$/', $basename, $ms)) {
      return null;
    }
    [$Y, $m, $d, $H, $M, $S] = [
      $ms[1], $ms[2], $ms[3],
      $ms[4], $ms[5], $ms[6],
    ];
    return new DateTime("$d/$m/$Y $H:$M:$S");
  }

  static function file(?string $filename): string {
    $vardir = path::join(app::get()->getVardir(), "pvs");
    if ($filename === null) return $vardir;
    else return "$vardir/$filename";
  }

  static function json_file(string $name): string {
    return self::file("$name.json");
  }

  static function storage_file(): string {
    return app::get()->getVarfile("pvs.db");
  }

  static function storage(): CapacitorStorage {
    $sqlite = new Sqlite(self::storage_file());
    return new SqliteStorage($sqlite);
  }

  static function channel(): PvChannel {
    $channel = new PvChannel();
    new Capacitor(self::storage(), $channel);
    return $channel;
  }

  static function channel_rebuilder(): PvChannelRebuilder {
    $channel = new PvChannelRebuilder();
    new Capacitor(self::storage(), $channel);
    return $channel;
  }
}
