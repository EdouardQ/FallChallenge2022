<?php

error_reporting(E_ALL);

function exception_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}

set_error_handler("exception_error_handler");

function debug($var) {
    error_log(var_export($var, true));
}

class Game {
    public $myMatter;
    public $oppMatter;
    public $map;
    public $myRobots;
    public $round;

    public $actions;

    public function init() {
        fscanf(STDIN, "%d %d", $width, $height);

        $this->map = new Map($width, $height);
        $this->actions = [];
        $this->round = 1;
    }

    public function run() {
        $this->init();

        while (true) {
            $this->parseInput();

            $this->think();

            $this->outputActions();
        }
    }

    public function move($amount, $fromX, $fromY, $toX, $toY) {
        $this->actions[] = ['MOVE', $amount, $fromX, $fromY, $toX, $toY];
    }

    public function build($x, $y) {
        $this->actions[] = ['BUILD', $x, $y];
    }

    public function spawn($amount, $x, $y) {
        $this->actions[] = ['SPAWN', $amount, $x, $y];
    }

    public function wait() {
        $this->actions[] = ['WAIT'];
    }

    public function message($text) {
        $this->actions[] = ['MESSAGE', $text];
    }

    public function parseInput() {
        fscanf(STDIN, "%d %d", $this->myMatter, $this->oppMatter);

        for ($y = 0; $y < $this->map->height; $y++) {
            for ($x = 0; $x < $this->map->width; $x++) {
                fscanf(STDIN, "%d %d %d %d %d %d %d", $scrapAmount, $owner, $units, $recycler, $canBuild, $canSpawn, $inRangeOfRecycler);
                $this->map->setTile($x, $y, $scrapAmount, $owner, $units, $recycler, $canBuild, $canSpawn, $inRangeOfRecycler);
            }
        }
    }

    public function outputActions() {
        if (empty($this->actions)) $this->wait();

        echo implode(';', array_map(function($a) {
            return implode(' ', $a);
        }, $this->actions)), PHP_EOL;
        $this->actions = [];
        $this->round++;
    }

    public function think() {
        $this->map->debug('scrapAmount');

        $this->protectBuild();
        $this->randomSpawn();
        $this->randomMove();
    }

    public function randomMove()
    {
        for ($y = 0; $y < $this->map->height; $y++) {
            for ($x = 0; $x < $this->map->width; $x++) {
                $tile = $this->map->getTile($x, $y);
                if ($tile->owner === 1 && $tile->units > 0) {
                    $leftRobots = $tile->units;

                    while ($leftRobots !== 0) {
                        $usedRobots = random_int(1, $leftRobots);

                        $move = $this->generateRandomCoordinates($x, $y);

                        if ($move) {
                            $this->move($usedRobots, $x, $y, $move[0], $move[1]);
                        }

                        $leftRobots -= $usedRobots;
                    }
                }
            }
        }
    }

    public function generateRandomCoordinates($x, $y)
    {
        $allMoves = $this->map->getAdjacentTiles($x, $y);
        $priorityMoves = [];



        for ($i = 0; $i < count($allMoves); $i++) {
            $tile = $this->map->getTile($allMoves[$i][0], $allMoves[$i][1]);

            if ($tile->scrapAmount > 0 && !$tile->recycler && !($tile->inRangeOfRecycler && $tile->scrapAmount === 1)) {
                $possibleMoves[] = $allMoves[$i];

                if ($tile->owner !== 1) {
                    $priorityMoves[] = $allMoves[$i];
                }
            }
        }

        if (!empty($priorityMoves)) {
            return $priorityMoves[random_int(0, count($priorityMoves)-1)];
        } elseif (!empty($possibleMoves)) {
            return $possibleMoves[random_int(0, count($possibleMoves)-1)];
        }

        return null;
    }

    public function randomSpawn()
    {
        if ($this->myMatter >=10) {
            $ennemyFrontier = [];
            $readyToExplore = [];
            $possibleSpawnLocation = [];

            for ($y = 0; $y < $this->map->height; $y++) {
                for ($x = 0; $x < $this->map->width; $x++) {
                    $tile = $this->map->getTile($x, $y);
                    if ($tile->canSpawn) {
                        foreach ($this->map->getAdjacentTiles($x, $y) as $adjacentTile) {
                            $tileToTest = $this->map->getTile($adjacentTile[0], $adjacentTile[1]);
                            if ($tileToTest->owner === 0 && !$tileToTest->recycler) {
                                $ennemyFrontier[] = [$x, $y];
                            } elseif ($tileToTest->owner === -1 && $tileToTest->scrapAmount > 0) {
                                $readyToExplore[] = [$x, $y];
                            }
                        }

                        $possibleSpawnLocation[] = [$x, $y];
                    }
                }
            }

            $nbrSpawn = random_int(1, intdiv($this->myMatter, 10));
            for ($i = 1; $i <= $nbrSpawn; $i++) {

                if (!empty($readyToExplore)) {
                    $coordinates = $readyToExplore[random_int(0, count($readyToExplore)-1)];
                } elseif (!empty($ennemyFrontier)) {
                    $coordinates = $ennemyFrontier[random_int(0, count($ennemyFrontier)-1)];
                } else {
                    $coordinates = $possibleSpawnLocation[random_int(0, count($possibleSpawnLocation)-1)];
                }

                $this->spawn(1, $coordinates[0], $coordinates[1]);
            }
        }
    }

    public function protectBuild()
    {
        for ($y = 0; $y < $this->map->height; $y++) {
            for ($x = 0; $x < $this->map->width; $x++) {
                $tile = $this->map->getTile($x, $y);
                if ($tile->owner === 1) {
                    $adjacentTiles = $this->map->getAdjacentTiles($x, $y);
                    foreach ($adjacentTiles as $adjacentTile) {
                        $tileToTest = $this->map->getTile($adjacentTile[0], $adjacentTile[1]);
                        if ($tileToTest->owner === 0 && $tileToTest->units > 0) {
                            $this->build($x, $y);
                            $this->map->getTile($x, $y)->recycler = true;
                            $this->myMatter -= 10;
                            break;
                        }
                    }
                }
            }
        }
    }
}

class Tile {
    public $scrapAmount = 0;
    public $owner = -1; // : 1 = me, 0 = foe, -1 = neutral
    public $units = 0;
    public $recycler = false;
    public $canBuild = false;
    public $canSpawn = false;
    public $inRangeOfRecycler = false;

}

class Map {
    public $width;
    public $height;
    public $tiles;
    private $BFS_CACHE;

    public function __construct($width, $height) {
        $this->width = $width;
        $this->height = $height;
        $this->tiles = [];

        for ($i=0; $i<$this->width*$this->height; $i++) {
            $this->tiles[] = new Tile();
        }
    }

    public function setTile($x, $y, $scrapAmount, $owner, $units, $recycler, $canBuild, $canSpawn, $inRangeOfRecycler) {
        if ($x < 0 || $x >= $this->width || $y < 0 || $y >= $this->height) throw new RuntimeException("Invalid setTile coord $x,$y");

        $tile = $this->tiles[$x+$y*$this->width];
        $tile->scrapAmount = (int)$scrapAmount;
        $tile->owner = (int)$owner;
        $tile->units = (int)$units;
        $tile->recycler = (bool)$recycler;
        $tile->canBuild = (bool)$canBuild;
        $tile->canSpawn = (bool)$canSpawn;
        $tile->inRangeOfRecycler = (bool)$inRangeOfRecycler;
    }

    public function getTile($x, $y) {
        if ($x < 0 || $x >= $this->width || $y < 0 || $y >= $this->height) throw new RuntimeException("Invalid getTile coord $x,$y");

        return $this->tiles[$x+$y*$this->width];
    }

    public function getAdjacentTiles($x, $y)
    {
        $tiles = [];

        if ($x-1 >= 0) {
            $tiles[] = [$x-1, $y];
        }
        if ($x+1 < $this->width) {
            $tiles[] = [$x+1, $y];
        }
        if ($y-1 >= 0) {
            $tiles[] = [$x, $y-1];
        }
        if ($y+1 < $this->height) {
            $tiles[] = [$x, $y+1];
        }

        return $tiles;
    }

    public function bfsDistance($sx, $sy, $dx, $dy) {
        // 5 bits per coordinate
        $key1 = ($sx<<0) | ($sy<<5) | ($dx<<10) | ($dy<<15);
        $key2 = ($sx<<0) | ($sy<<5) | ($dx<<10) | ($dy<<15);

        if (isset($this->BFS_CACHE[$key1])) {
            return $this->BFS_CACHE[$key1];
        }

        $width = $this->width;

        $i = $dx+$dy*$width;
        if ($this->tiles[$i]->recycler==true || $this->tiles[$i]->scrapAmount==0) {
            return $this->BFS_CACHE[$key1] = $this->BFS_CACHE[$key2] = -1;
        }

        $vIndex = 1;
        $visited = array_fill(0, $this->width*$this->height, 0);

        $q = [];
        $qCount = $qIndex = 0;

        $visited[$sx+$sy*$width] = $vIndex;
        $q[$qCount++] = ($sx<<16) | ($sy<<8) | (0);

        while ($qCount > $qIndex) {
            $m = $q[$qIndex++];
            $nx = ($m>>16) & 0xFF;
            $ny = ($m>>8) & 0xFF;
            $nd = ($m>>0) & 0xFF;

            // Cache distance between start and all nodes along the way ^^
            $this->BFS_CACHE[($sx<<0) | ($sy<<5) | ($nx<<10) | ($ny<<15)] =
            $this->BFS_CACHE[($nx<<0) | ($ny<<5) | ($sx<<10) | ($sy<<15)] =
                $nd;

            if ($nx == $dx && $ny == $dy) {
                return $nd;
            }

            $i = $nx+1 + $ny*$width;
            if ($nx < $this->width-1 && $visited[$i] < $vIndex && $this->tiles[$i]->recycler==false && $this->tiles[$i]->scrapAmount>0) {
                $visited[$i] = $vIndex;
                $q[$qCount++] = (($nx+1)<<16) | (($ny)<<8) | ($nd+1);
            }

            $i = $nx-1 + $ny*$width;
            if ($nx > 0 && $visited[$i] < $vIndex && $this->tiles[$i]->recycler==false && $this->tiles[$i]->scrapAmount>0) {
                $visited[$i] = $vIndex;
                $q[$qCount++] = (($nx-1)<<16) | (($ny)<<8) | ($nd+1);
            }

            $i = $nx + ($ny+1)*$width;
            if ($ny < $this->height-1 && $visited[$i] < $vIndex && $this->tiles[$i]->recycler==false && $this->tiles[$i]->scrapAmount>0) {
                $visited[$i] = $vIndex;
                $q[$qCount++] = (($nx)<<16) | (($ny+1)<<8) | ($nd+1);
            }

            $i = $nx + ($ny-1)*$width;
            if ($ny > 0 && $visited[$i] < $vIndex && $this->tiles[$i]->recycler==false && $this->tiles[$i]->scrapAmount>0) {
                $visited[$i] = $vIndex;
                $q[$qCount++] = (($nx)<<16) | (($ny-1)<<8) | ($nd+1);
            }
        }

        return $this->BFS_CACHE[$key1] = $this->BFS_CACHE[$key2] = -1;
    }

    public function debug($field = 'scrapAmount') {
        if (!property_exists($this->tiles[0], $field)) throw new RuntimeException("Unknow debug field '$field'");

        $boolToStr = [false => '.', true => 'X'];

        error_log(' '.str_pad(" $field ", $this->width*3+1, '-', STR_PAD_BOTH));
        for ($y=0; $y<$this->height; $y++) {
            $line = [];
            for ($x=0; $x<$this->width; $x++) {
                $val = $this->getTile($x, $y)->$field;
                $line[] = str_pad(is_bool($val) ? $boolToStr[$val] : $val, 2, ' ', STR_PAD_LEFT);
            }
            error_log('| ' . implode(' ', $line) . ' |');
        }
        error_log(' '.str_repeat('-', $this->width*3+1));
    }
}

$game = new Game();
$game->run();