<?php
// test stand-in for Commons: fixed photos per place, so the finder's own rules are what's tested
final class StockWikimedia {
  public static array $calls = [];
  private function ph($id, $title, $lat, $lon, $w = 1200, $lic = 'CC BY-SA 4.0') { return ['id' => $id, 'title' => $title, 'lat' => $lat, 'lon' => $lon, 'width' => $w, 'licence' => $lic, 'rights' => '', 'full' => 'https://upload.wikimedia.org/x/' . rawurlencode($id), 'source_url' => 'https://commons.wikimedia.org/wiki/File:' . rawurlencode($id), 'author' => 'Tester <b>A</b>', 'about' => '', 'tags' => '']; }
  public function nearby(float $lat, float $lon, int $r = 1500, int $l = 40): array {
    self::$calls[] = "nearby $lat,$lon";
    return [ $this->ph('Painted_Desert_Visitor_Center.jpg', 'Painted Desert Visitor Center', 35.066, -109.783),   // the place: matches
             $this->ph('Painted_Desert_Inn.jpg', 'Painted Desert Inn', 35.084, -109.790),                         // another place nearby: not every word
             $this->ph('Petrified_Forest_National_Park_tiny.jpg', 'Petrified Forest National Park', 34.91, -109.81, 300),   // too small
             $this->ph('Petrified_Forest_National_Park_sign.jpg', 'Petrified Forest National Park sign', 34.91, -109.81, 1600, 'All rights reserved') ];   // not free
  }
  public function named(string $q, int $l = 20): array { self::$calls[] = "named $q"; return $q === 'Petrified Forest National Park' ? [$this->ph('Petrified_Forest_National_Park_Blue_Mesa.jpg', 'Petrified Forest National Park, Blue Mesa', 34.94, -109.76)] : []; }
}
