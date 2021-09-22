<?php

namespace Lifo\Color;

use Colors\RandomColor;


/**
 * Simple Color class representing a single color. Provides methods to convert the color into different formats for
 * use in CSS, or to generate other colors or palettes.
 *
 * @property int $r Red color value
 * @property int $g Green color value
 * @property int $b Blue color value
 * @property int $a Alpha value
 * @link https://www.codeproject.com/Articles/1202772/Color-Topics-for-Programmers
 * @link http://www.nbdtech.com/Blog/archive/2008/04/27/Calculating-the-Perceived-Brightness-of-a-Color.aspx
 */
class Color implements \ArrayAccess
{
    const GREYSCALE_LUMINOSITY = 'luminosity';
    const GREYSCALE_AVERAGE    = 'average';
    const GREYSCALE_LIGHTNESS  = 'lightness';

    /** @var int[] internal color representation is always in RGB */
    private $color;
    /** @var int Alpha channel */
    private $alpha;

    /**
     * @param mixed $color RGB, HSL, HSV color string or array
     * @param int   $alpha Optional Alpha channel
     */
    public function __construct($color, $alpha = null)
    {
        $this->alpha = ($alpha !== null && $alpha !== '') ? (int)$alpha : null;
        $this->setColor($color);
    }

    /**
     * Return the RGB HEX string for the color
     *
     * @return string
     */
    public function __toString()
    {
        return $this->hex();
    }

    /**
     * Return color as RGB hex string, for use in CSS
     *
     * @param bool $ary If true, return an array of values
     *
     * @return string|array
     */
    public function hex($ary = false)
    {
        $list = $this->color;
        if ($this->alpha !== null) {
            $list['a'] = $this->alpha;
        }
        return $ary ? $list : '#' . implode('', array_map(function ($c) {
                return str_pad(dechex($c), 2, '0', STR_PAD_LEFT);
            }, $list));
    }

    /**
     * Return color as RGB function string, for use in CSS
     *
     * @param bool $ary If true, return an array of values
     *
     * @return string|array
     */
    public function rgb($ary = false)
    {
        $list = $this->color;
        $func = 'rgb';
        if ($this->alpha !== null) {
            $list['a'] = $this->alpha;
            $func .= 'a';
        }

        return $ary ? $list : "$func(" . implode(',', $list) . ")";
    }

    /**
     * Return current color as HSL function string, for use in CSS
     *
     * @param bool $ary If true, return an array of values
     *
     * @return string|array
     */
    public function hsl($ary = false)
    {
        $color = self::rgb2hsl($this->color);
        if ($ary) return $color;
        $func = 'hsl';
        if ($this->alpha !== null) {
            $color['a'] = $this->alpha;
            $func .= 'a';
        }
        $str = $func . '(';
        foreach ($color as $k => $c) {
            $str .= $c;
            if (!in_array($k, ['h', 'a'])) {
                $str .= '%';
            }
            $str .= ',';
        }
        $str = rtrim($str, ',') . ')';
        return $str;
    }

    /**
     * Return current color as HSV function string, for use in CSS
     *
     * @param bool $ary If true, return an array of values
     *
     * @return string|array
     */
    public function hsv($ary = false)
    {
        $color = self::rgb2hsv($this->color);
        if ($ary) return $color;
        $func = 'hsv';
        if ($this->alpha !== null) {
            $color['a'] = $this->alpha;
            $func .= 'a';
        }
        $str = $func . '(';
        foreach ($color as $k => $c) {
            $str .= $c;
            if (!in_array($k, ['h', 'a'])) {
                $str .= '%';
            }
            $str .= ',';
        }
        $str = rtrim($str, ',') . ')';
        return $str;
    }

    /**
     * Return current color as CMY string
     *
     * @param bool $ary If true, return an array of values
     *
     * @return string
     */
    public function cmy($ary = false)
    {
        $color = self::rgb2cmy($this->color);
        if ($ary) return $color;
        $str = 'cmy(' . implode(',', $color) . ')';
        return $str;
    }

    /**
     * Return current color as CMYK string
     *
     * @param bool $ary If true, return an array of values
     *
     * @return string|array
     */
    public function cmyk($ary = false)
    {
        $color = self::rgb2cmyk($this->color);
        if ($ary) return $color;
        $str = 'cmyk(' . implode(',', $color) . ')';
        return $str;
    }

    /**
     * Returns an array for each color calculation. Mainly useful for debugging purposes.
     *
     * @return string[][]
     */
    public function all()
    {
        $res = [];
        foreach (['rgb', 'hsl', 'hsv', 'cmy', 'cmyk'] as $name) {
            $res[$name] = $this->$name(true);
        }
//        $res['complementary'] = $this->complementary()->rgb(true);
//        $res['contrast'] = $this->contrast()->rgb(true);
//        $res['triadic'] = array_map(function($c){ return $c->rgb(true); }, $this->triadic());
        return $res;
    }

    /**
     * Set the color.
     *
     * @param mixed $color HEX string, or array with keyed color values. Arrays must be keyed with
     *                     'r','g','b','h','s','v','c','m','k' or 'l' to identify the color calculations to use.
     *                     If numeric keys are used then 'r', 'g', 'b' is assumed.
     *
     * @return Color
     */
    public function setColor($color)
    {
        $this->color = self::toRGB($color);
        if (!$this->color) {
            throw new \InvalidArgumentException("Invalid color argument #1 to " . __METHOD__ . '(' . json_encode($color) . ')');
        }
        if (count($this->color) > 3) {
            $this->alpha = isset($this->color[3]) ? $this->color[3] : isset($this->color['a']) ? $this->color['a'] : null;
            if (isset($this->color['a'])) {
                unset($this->color['a']);
            } else {
                $this->color = array_slice($this->color, 0, 3);
            }
        }

        return $this;
    }

    /**
     * @param int $alpha
     *
     * @return Color
     */
    public function setAlpha($alpha)
    {
        $this->alpha = $alpha === null ? null : (int)$alpha;

        return $this;
    }

    /**
     * Lighten the color by the percentage. Returns a new Color instance
     *
     * @param int $percent Integer between 0..100
     *
     * @return $this
     */
    public function lighten($percent)
    {
        return self::luminance($this->color, $percent / 100);
    }

    /**
     * Darken the color by the percentage. Returns a new Color instance
     *
     * @param int $percent Integer between 0..100
     *
     * @return $this
     */
    public function darken($percent)
    {
        return self::luminance($this->color, -$percent / 100);
    }

//    /**
//     * Calculate the brightness (luminance) of the color.
//     *
//     * @link https://www.codeproject.com/Articles/1202772/Color-Topics-for-Programmers#Relative_Luminance_Grayscale
//     * @return float
//     */
//    public function brightness()
//    {
//        return $this->color['r'] * 0.2126 + $this->color['g'] * 0.7152 + $this->color['b'] * 0.0722;
//    }

    /**
     * Calculate the brightness (luminance) of the color.
     *
     * @link http://www.nbdtech.com/Blog/archive/2008/04/27/Calculating-the-Perceived-Brightness-of-a-Color.aspx
     * @return float
     */
    public function brightness()
    {
        return sqrt(
        // r/g/b values are weighted for human perception and sum == 1.0
            $this->color['r'] * $this->color['r'] * .299 +
            $this->color['g'] * $this->color['g'] * .587 +
            $this->color['b'] * $this->color['b'] * .114
        );
    }

    /**
     * Return a contrasting color. Useful for displaying one color as a background and the contrasting color as the
     * foreground color.
     */
    public function contrast()
    {
        return new self($this->brightness() < 130 ? '#fff' : '#000');
//        return new self(hexdec(trim($this->rgb(), '#')) > 0xffffff / 2 ? [0, 0, 0] : [255, 255, 255]);
    }

    public function greyscale($how = self::GREYSCALE_LUMINOSITY)
    {
        return self::toGreyscale($this->color, $how);
    }

    /**
     * Convert the color into greyscale. Returns a new Color instance.
     *
     * @param Color|string|array $color Color to transform
     * @param string             $how   One of the Color::GREYSCALE_* constants; how to calculate the greyscale
     *
     * @return $this
     */
    public static function toGreyscale($color, $how = self::GREYSCALE_LUMINOSITY)
    {
        $color = $color instanceof self ? $color : new static($color);
        switch ($how) {
            case self::GREYSCALE_AVERAGE:
                $x = array_sum($color->color) / 3;
                break;
            case self::GREYSCALE_LIGHTNESS:
                $x = (max($color->color) + min($color->color)) / 2;
                break;
            default:
            case self::GREYSCALE_LUMINOSITY:
                $x = 0.21 * $color->r + 0.72 * $color->g + 0.07 * $color->b;
                break;
        }
        $x = round($x);
        return new static([$x, $x, $x], $color->alpha);
    }

    /**
     * Return the complementary color for the current color.
     *
     * @return $this
     */
    public function complementary()
    {
        $c = $this->hsl(true);
        $c['h'] += 180 % 360;

        // same as:
//        $list = $this->rotate(2, 'hue', 360, 180);
//        return $list[1];

        // or this:
//        $c = $this->hsl(true);
//        $c['h'] = $c['h'] / 360 + 0.5;
//        if ($c['h'] > 1) $c['h'] -= 1;
//        $c['h'] *= 360;

        return new self($c);
    }

    /**
     * Return the split complementary colors (3) for the current color. Similar to Triadic
     *
     * @return $this[]
     */
    public function splitComplementary()
    {
        $c1 = $c2 = $this->hsl(true);
        $c1['h'] += 150 % 360;
        $c2['h'] += 210 % 360;
        $palette = [clone $this];
        $palette[] = new self($c1);
        $palette[] = new self($c2);

        return $palette;
    }

//    public function blend($color, $alpha = 1)
//    {
//        return self::lerp3($this->color, $color, $alpha);
//    }

    /**
     * A three (3) color list of colors based on the current color
     *
     * @return $this[]
     */
    public function triadic()
    {
        return $this->rotate(3);
    }

    /**
     * A four (4) color list of colors based on the current color
     *
     * @return $this[]
     */
    public function tetradic()
    {
        return $this->rotate(4);
//        $c1 = $c2 = $c3 = $this->hsl(true);
//        $c1['h'] += 90 % 360;
//        $c2['h'] += 180 % 360;
//        $c3['h'] += 270 % 360;
//        $palette = [clone $this];
//        $palette[] = new self($c1);
//        $palette[] = new self($c2);
//        $palette[] = new self($c3);
//        return $palette;
    }

    /**
     * 1 or more related colors based on the current color
     *
     * @param int $total
     *
     * @return array
     */
    public function analogous($total = 2)
    {
        $c = $this->hsl(true);
        $palette = [clone $this];
        for ($i = 1; $i <= $total; $i++) {
            $c1 = $c;
            // 30 degrees apart
            $c1['h'] += 30 * $i % 360;
            $palette[] = new self($c1);
        }

        return $palette;
    }

    /**
     * Return a list of shades of the current color. The last color will always be black #000000
     *
     * @param int $total Total shades to calculate
     *
     * @return $this[]
     */
    public function shades($total = 10)
    {
        $c = $this->hsl(true);
        $step = $c['l'] / $total;
        $palette = [clone $this];
        for ($i = 1; $i <= $total; $i++) {
            $l = $c['l'] - $step * $i;
            $palette[] = new self(['h' => $c['h'], 's' => $c['s'], 'l' => $l]);
        }

        return $palette;
    }

    /**
     * Return a list of tints of the current color. The last color will always be white #FFFFFF
     *
     * @param int $total Total shades to calculate
     *
     * @return $this[]
     */
    public function tints($total = 10)
    {
        $c = $this->hsl(true);
        $step = (100 - $c['l']) / $total;
        $palette = [clone $this];
        for ($i = 1; $i <= $total; $i++) {
            $l = $c['l'] + $step * $i;
            $palette[] = new self(['h' => $c['h'], 's' => $c['s'], 'l' => $l]);
        }

        return $palette;
    }

    /**
     * Internal method to calculate rotational dispersion around the color wheel.
     *
     * @param int    $count
     * @param string $type
     * @param int    $scope 0..360 degrees
     * @param int    $rotation
     *
     * @return $this[]
     */
    public function rotate($count, $type = 'hue', $scope = 360, $rotation = 0)
    {
        $c = $this->hsv(true);
        $h = $c['h'] / 360;
        $s = $c['s'] / 100;
        $v = $c['v'] / 100;

        // if scope is 360, the start and end point are the same color, so should be avoided, otherwise enlarge the steps
        $steps = ($type == 'hue' && ($scope == 360 || $scope == 0)) ? $scope / $count : $scope / ($count - 1);
        // if scope is 360, start on the current color
        $origin = ($scope == 360) ? 0 : self::degrees(self::degrees($h, $rotation), -1 * $scope / 2);

        $palette = [];
        for ($i = 0; $i < $count; $i++) {
            $offset = $steps * $i;
            switch ($type) {
                case "hue":
                    $d = self::degrees($origin, $offset);
                    $palette[] = new self(['h' => $d, 's' => $s, 'v' => $v]);
                    break;
                case "saturation":
                    $palette[] = new self(['h' => $h, 's' => $offset, 'v' => $v]);
                    break;
                case "value":
                case "lightness":
                case "brightness":
                    $palette[] = new self(['h' => $h, 's' => $s, 'v' => $offset]);
                    break;
            }
        }

        return $palette;
    }

    /**
     * Apply optional offset and normal degrees and maintain boundaries within 0..359
     *
     * @param $degrees
     * @param $offset
     *
     * @return int
     */
    protected static function degrees($degrees, $offset = 0)
    {
        return ($degrees + $offset) % 360;
    }

    /**
     * @param     $color1
     * @param     $color2
     * @param int $alpha
     *
     * @return Color
     */
    static function lerp3($color1, $color2, $alpha = 1)
    {
        $color1 = $color1 instanceof self ? $color1 : new self($color1);
        $color2 = $color2 instanceof self ? $color2 : new self($color2);
        return new self([
            $color1[0] + ($color2[0] - $color1[0]) * $alpha,
            $color1[1] + ($color2[1] - $color1[1]) * $alpha,
            $color1[2] + ($color2[2] - $color1[2]) * $alpha,
        ]);
    }

    /**
     * Change the luminance (brightness) of the color (make it lighter or darker) by a percentage [-1..1]
     *
     * @param array|string $color   Any color string or array
     * @param int          $percent Float between -1 .. 1
     * @param bool         $alpha   If true the alpha value will be transferred to the returned Color
     *
     * @return Color
     */
    static function luminance($color, $percent, $alpha = false)
    {
        $rgb = self::toRGB($color);
        if (!$rgb) {
            throw new \InvalidArgumentException("Invalid color argument #1 to " . __METHOD__ . '(' . json_encode($color) . ')');
        }
        $a = ($alpha && isset($rgb['a'])) ? $rgb['a'] : null;
        unset($rgb['a']);
        foreach ($rgb as $k => $byte) {
            $from = $percent < 0 ? 0 : $byte;
            $to = $percent < 0 ? $byte : 255;
            $value = ceil(($to - $from) * $percent);
            $rgb[$k] = $byte + $value;
        }

        return new static($rgb, $a);
    }

    /**
     * Convert color from RGB to HSL.
     *
     * @param int[] $color Color array.
     *
     * @return int[] [H,S,L] keyed color array
     */
    public static function rgb2hsl($color)
    {
        $type = self::determineType($color, 'rgb');
        if ($type == 'hsl') {
            return $color;
        } elseif ($type != 'rgb') {
            throw new \InvalidArgumentException(sprintf("Invalid color in %s::%s(%s)", __CLASS__, __FUNCTION__, json_encode($color)));
        }

        $r = $color['r'] / 255;
        $g = $color['g'] / 255;
        $b = $color['b'] / 255;

        $min = min($r, $g, $b);
        $max = max($r, $g, $b);
        $d = $max - $min;

        $h = 0;
        $s = 0;
        $l = ($max + $min) / 2;

        if ($d != 0) {
            $s = $d / (1 - abs(2 * $l - 1));

            if ($r == $max) {
                $h = 60 * fmod((($g - $b) / $d), 6);
                if ($b > $g) {
                    $h += 360;
                }
            } elseif ($g == $max) {
                $h = 60 * (($b - $r) / $d + 2);
            } elseif ($b == $max) {
                $h = 60 * (($r - $g) / $d + 4);
            }
        }

        return [
            'h' => round($h),
            's' => round($s * 100, 3),
            'l' => round($l * 100, 3),
        ];
    }

    /**
     * Convert color from RGB to HSV.
     *
     * @param int[] $color Color array.
     *
     * @return int[] [H,S,V] keyed color array
     */
    public static function rgb2hsv($color)
    {
        $type = self::determineType($color, 'rgb');
        if ($type == 'hsv') {
            return $color;
        } elseif ($type != 'rgb') {
            throw new \InvalidArgumentException(sprintf("Invalid color in %s::%s(%s)", __CLASS__, __FUNCTION__, json_encode($color)));
        }

        $r = $color['r'] / 255;
        $g = $color['g'] / 255;
        $b = $color['b'] / 255;

        $min = min($r, $g, $b);
        $max = max($r, $g, $b);
        $d = $max - $min;

        $h = 0;
        $s = 0;
        $v = $max;

        if ($d != 0) {
            $s = $d / $max;

            if ($r == $max) {
                $h = 60 * fmod((($g - $b) / $d), 6);
                if ($b > $g) {
                    $h += 360;
                }
            } elseif ($g == $max) {
                $h = 60 * (($b - $r) / $d + 2);
            } elseif ($b == $max) {
                $h = 60 * (($r - $g) / $d + 4);
            }
        }

        return [
            'h' => round($h),
            's' => round($s * 100, 3),
            'v' => round($v * 100, 3),
        ];
    }

    /**
     * Convert color from HSV to RGB.
     *
     * @param int[] $color Color array.
     *
     * @return int[] [R,G,B] keyed color array
     */
    public static function hsv2rgb($color)
    {
        $type = self::determineType($color, 'hsv');
        if ($type == 'rgb') {
            return $color;
        } elseif ($type != 'hsv') {
            throw new \InvalidArgumentException(sprintf("Invalid color in %s::%s(%s)", __CLASS__, __FUNCTION__, json_encode($color)));
        }

        $h = $color['h'] % 360 / 360;
        $s = $color['s'];
        $v = $color['v'];

        if ($s == 0) {
            $r = $g = $b = $v;
        } else {
            $h *= 6;
            if ($h == 6) $h = 0;
            $i = floor($h);
            $v1 = $v * (1 - $s);
            $v2 = $v * (1 - $s * ($h - $i));
            $v3 = $v * (1 - $s * (1 - ($h - $i)));

            switch ($i) {
                case 0:
                    $r = $v;
                    $g = $v3;
                    $b = $v1;
                    break;
                case 1:
                    $r = $v2;
                    $g = $v;
                    $b = $v1;
                    break;
                case 2:
                    $r = $v1;
                    $g = $v;
                    $b = $v3;
                    break;
                case 3:
                    $r = $v1;
                    $g = $v2;
                    $b = $v;
                    break;
                case 4:
                    $r = $v3;
                    $g = $v1;
                    $b = $v;
                    break;
                default:
                    $r = $v;
                    $g = $v1;
                    $b = $v2;
            }
        }

        return [
            'r' => ceil($r * 255),
            'g' => ceil($g * 255),
            'b' => ceil($b * 255),
        ];
    }

    /**
     * Convert color from RGB to CMY.
     *
     * @param int[] $color Color array.
     *
     * @return int[] [C,M,Y] keyed color array
     */
    public static function rgb2cmy($color)
    {
        $type = self::determineType($color, 'rgb');
        if ($type == 'cmy') {
            return $color;
        } elseif ($type != 'rgb') {
            throw new \InvalidArgumentException(sprintf("Invalid color in %s::%s(%s)", __CLASS__, __FUNCTION__, json_encode($color)));
        }

        return [
            'c' => round(1 - $color['r'] / 255, 3),
            'm' => round(1 - $color['g'] / 255, 3),
            'y' => round(1 - $color['b'] / 255, 3),
        ];
    }

    public static function rgb2cmyk($color)
    {
        $cmy = self::rgb2cmy($color);

        $c = $cmy['c'];
        $m = $cmy['m'];
        $y = $cmy['y'];
        $k = 1;

        if ($c < $k) $k = $c;
        if ($m < $k) $k = $m;
        if ($y < $k) $k = $y;
        if ($k == 1) {
            $c = $m = $y = 0;
        } else {
            $c = ($c - $k) / (1 - $k);
            $m = ($m - $k) / (1 - $k);
            $y = ($y - $k) / (1 - $k);
        }

        return [
            'c' => round($c * 100, 3),
            'm' => round($m * 100, 3),
            'y' => round($y * 100, 3),
            'k' => round($k * 100, 3),
        ];

    }

    public static function cmyk2cmy($color)
    {
        $type = self::determineType($color, 'cmyk');
        if ($type == 'cmy') {
            return $color;
        } elseif ($type != 'cmyk') {
            throw new \InvalidArgumentException(sprintf("Invalid color in %s::%s(%s)", __CLASS__, __FUNCTION__, json_encode($color)));
        }

        return [
            'c' => $color['c'] * (1 - $color['k']) / $color['k'],
            'm' => $color['m'] * (1 - $color['k']) / $color['k'],
            'y' => $color['y'] * (1 - $color['k']) / $color['k'],
        ];
    }

    /**
     * Convert color from CMY to RGB.
     *
     * @param int[] $color Color array.
     *
     * @return int[] [R,G,B] keyed color array
     */
    public static function cmy2rgb($color)
    {
        $type = self::determineType($color, 'cmy');
        if ($type == 'rgb') {
            return $color;
        } elseif ($type != 'cmy') {
            throw new \InvalidArgumentException(sprintf("Invalid color in %s::%s(%s)", __CLASS__, __FUNCTION__, json_encode($color)));
        }

        return [
            'r' => (int)((1 - $color['c']) * 255),
            'g' => (int)((1 - $color['m']) * 255),
            'b' => (int)((1 - $color['y']) * 255),
        ];
    }

    /**
     * Convert color from CMYK to RGB.
     *
     * @param int[] $color Color array.
     *
     * @return int[] [R,G,B] keyed color array
     */
    public static function cmyk2rgb($color)
    {
        $type = self::determineType($color, 'cmyk');
        if ($type == 'rgb') {
            return $color;
        } elseif ($type != 'cmyk') {
            throw new \InvalidArgumentException(sprintf("Invalid color in %s::%s(%s)", __CLASS__, __FUNCTION__, json_encode($color)));
        }

        return self::cmy2rgb(self::cmyk2cmy($color));
    }

    /**
     * Convert color from HSL to RGB.
     *
     * @param int[] $color Color array.
     *
     * @return int[] [R,G,B] keyed color array
     */
    public static function hsl2rgb($color)
    {
        $type = self::determineType($color, 'hsl');
        if ($type == 'rgb') {
            return $color;
        } elseif ($type != 'hsl') {
            throw new \InvalidArgumentException(sprintf("Invalid color in %s::%s(%s)", __CLASS__, __FUNCTION__, json_encode($color)));
        }

        $h = $color['h'] / 360;
        $s = $color['s'] / 100;
        $l = $color['l'] / 100;

        if ($s == 0) {
            $v = ceil($l * 255);
            return [
                'r' => $v,
                'g' => $v,
                'b' => $v,
            ];
        }

        $var2 = $l < 0.5 ? $l * (1 + $s) : ($l + $s) - ($s * $l);
        $var1 = 2 * $l - $var2;

        return [
            'r' => (int)ceil(255 * self::hue2rgb($var1, $var2, $h + (1 / 3))),
            'g' => (int)ceil(255 * self::hue2rgb($var1, $var2, $h)),
            'b' => (int)ceil(255 * self::hue2rgb($var1, $var2, $h - (1 / 3))),
        ];
    }

    /**
     * Generate a list of random colors using the RandomColor library
     *
     * @param int   $count
     * @param array $options
     *
     * @return Color[]
     */
    public static function many($count, $options = [])
    {
        return array_map(function ($c) {
            return new self($c);
        }, RandomColor::many($count, $options));
    }

    /**
     * Generate 1 random color using the RandomColor library
     *
     * @param array $options
     *
     * @return Color
     */
    public static function one($options = [])
    {
        return new self(RandomColor::one($options));
    }

    private static function hue2rgb($v1, $v2, $vh)
    {
        if ($vh < 0) {
            $vh += 1;
        } elseif ($vh > 1) {
            $vh -= 1;
        }

        if ((6 * $vh) < 1) {
            return $v1 + ($v2 - $v1) * 6 * $vh;
        }

        if ((2 * $vh) < 1) {
            return $v2;
        }

        if ((3 * $vh) < 2) {
            return $v1 + ($v2 - $v1) * ((2 / 3) - $vh) * 6;
        }

        return $v1;
    }

    /**
     * Extract the keyed numbers from the color array given. If any keys are missing than 0 is used instead.
     *
     * @param array  $color
     * @param string $str string keys to extract, eg: 'rgb', 'hsl', 'hsv'
     *
     * @return array
     */
    private static function extractNumbers(array $color, $str)
    {
        $numbers = [];
        foreach (str_split($str) as $c) {
            $numbers[$c] = isset($color[$c]) ? $color[$c] : 0;
        }
        return $numbers;
    }

    /**
     * Try to determine the type of color represented in the variable given.
     *
     * @param mixed  $color   The color to check. Variable is a reference and will be updated after this is called
     * @param string $default If numeric indexes are found in $color array then use this as default color type ('rgb', 'hsl', etc)
     *
     * @return string|boolean False on failure, or the color type as a string. ie: 'rgb', 'hsl', 'hsv'
     */
    private static function determineType(&$color, $default = 'rgb')
    {
        if (is_array($color)) {
            switch (true) {
                case isset($color['r']):
                    $numbers = self::extractNumbers($color, 'rgb');
                    // alpha handled separately
                    if (isset($color['a'])) {
                        $numbers['a'] = $color['a'];
                    }
                    $color = $numbers;
                    return 'rgb';
                case isset($color['l']):
                    $color = self::extractNumbers($color, 'hsl');
                    return 'hsl';
                case isset($color['v']):
                    $color = self::extractNumbers($color, 'hsv');
                    return 'hsv';
                case isset($color['k']):
                    $color = self::extractNumbers($color, 'cmyk');
                    return 'cmyk';
                case isset($color['c']):
                    $color = self::extractNumbers($color, 'cmy');
                    return 'cmy';
                case isset($color[0]):
                    $color = array_values($color); // make sure values re-indexed
                    $numbers = [];
                    foreach (str_split($default) as $i => $c) {
                        $numbers[$c] = isset($color[$i]) ? $color[$i] : 0;
                    }
                    if (count($color) > 3) {
                        $numbers['a'] = $color[3];
                    }
                    $color = $numbers;
                    return str_replace('a', '', $default);
            }
        } else {
            switch (true) {
                case $color instanceof self:
                    if (method_exists($color, $default)) {
                        $color = $color->$default(true);
                        return $default;
                    }
                    break;
                // match: rgb(1,2,3), ...
                case preg_match('/^(\w+)\(([^)]+)\)+/', $color, $m):
                    $keys = array_unique(str_split($m[1]));
                    $values = trim(preg_replace('/[^\d]+/', ' ', $m[2]));
                    $values = array_map('intval', array_map('trim', explode(' ', $values)));
                    // make sure keys and values are the same length
                    if (count($values) > count($keys)) {
                        // remove values that do not have a matching key
                        $values = array_slice($values, 0, count($keys));
                    } elseif (count($values) < count($keys)) {
                        // remove keys that do not have a matching value
                        $keys = array_slice($keys, 0, count($values));
                    }
                    $color = array_combine($keys, $values);
                    return self::determineType($color);
                case strpos($color, '#') !== false || preg_match('/^#?[a-zA-Z0-9]+$/', $color):
                    $color = trim($color, '# ');
                    if (strlen($color) < 6) {
                        $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
                    }
                    $numbers = array_map('hexdec', str_split(str_pad($color, 6, '0'), 2));
                    $color = ['r' => $numbers[0], 'g' => $numbers[1], 'b' => $numbers[2]];
                    if (count($numbers) > 3) {
                        $color['a'] = $numbers[3];
                    }
                    return 'rgb';
            }
        }

        return false;
    }

    /**
     * Convert the color to RGB
     *
     * @param $color
     *
     * @return int[]|boolean
     */
    private static function toRGB($color)
    {
        $numbers = $color;
        $type = self::determineType($numbers);
        $func = $type . '2rgb';
        switch (true) {
            case $type == 'rgb':
                return $numbers;
            case method_exists(self::class, $func):
                return self::$func($numbers);
        }

        return false;
    }

    /**
     * Normalize the offset index for use in ArrayAccess methods
     *
     * @param string|int $offset
     *
     * @return string False if offset is invalid, or a color string name, eg: "r", "g", "b", etc.
     */
    private function normalizeOffset($offset)
    {
        static $map = [
            0 => 'r',
            1 => 'g',
            2 => 'b',
            3 => 'a',
        ];
        if (isset($map[$offset])) {
            return $map[$offset];
        } elseif (in_array($offset, ['r', 'g', 'b', 'a'])) {
            return $offset;
        }
        return false;
    }

    public function __get($name)
    {
        $offset = $this->normalizeOffset($name);
        if (!$offset) {
            $t = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            trigger_error(sprintf("Undefined property: %s::$%s in file %s at line %d", __CLASS__, $name, $t[0]['file'], $t[0]['line']));
            return null;
        }
        return $offset == 'a' ? $this->alpha : $this->color[$offset];
    }

    public function __set($name, $value)
    {
        $offset = $this->normalizeOffset($name);
        if (!$offset) {
            $t = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            trigger_error(sprintf("Undefined property: %s::$%s in file %s at line %d", __CLASS__, $name, $t[0]['file'], $t[0]['line']));
            return null;
        }
        if ($offset == 'a') {
            $this->alpha = $value;
        } else {
            $this->color[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return $this->normalizeOffset($offset) !== false;
    }

    public function offsetGet($offset)
    {
        $offset = $this->normalizeOffset($offset);
        if ($offset == 'a') return $this->alpha;
        return $offset !== false ? $this->color[$offset] : null;
    }

    public function offsetSet($offset, $value)
    {
        $offset = $this->normalizeOffset($offset);
        if ($offset == 'a') {
            $this->alpha = $value;
        } elseif ($offset !== false) {
            $this->color[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        // don't unset, just set it to 0
        $this->offsetSet($offset, 0);
    }
}