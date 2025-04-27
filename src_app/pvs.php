<?php
namespace app;

use nulib\app;
use nulib\db\Capacitor;
use nulib\db\CapacitorStorage;
use nulib\db\sqlite\Sqlite;
use nulib\db\sqlite\SqliteStorage;
use nulib\file;
use nulib\os\path;
use nulib\php\time\DateTime;

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

  static function upload_file(?string $filename): string {
    $vardir = path::join(app::get()->getVardir(), "uploads");
    if ($filename === null) return $vardir;
    else return "$vardir/$filename";
  }

  static function file(?string $filename): string {
    $vardir = path::join(app::get()->getVardir(), "pvs");
    if ($filename === null) return $vardir;
    else return "$vardir/$filename";
  }

  static function json_file(string $name): string {
    return self::file("$name.json");
  }

  static function json_data(string $name): ?array {
    $file = self::json_file($name);
    if (file_exists($file)) {
      $data = file::reader($file)->decodeJson();
      if ($data) return $data;
    }
    return null;
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
