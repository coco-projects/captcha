<?php

    namespace Coco\captcha;

class Captcha
{
    private string         $code;
    private int            $width  = 140;
    private int            $height = 60;
    private int            $length = 4;
    private \GdImage|false $img;

    public function __construct(int $length, int $width = 140, int $height = 60)
    {
        $this->length = $length;
        $this->width  = $width;
        $this->height = $height;

        $this->make();
    }

    public function showImage(): void
    {
        $img = $this->img;
        ob_get_clean();

        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');

        if (function_exists("imagejpeg")) {
            header("Content-Type: image/jpeg");
            imagejpeg($img, null, 90);//图片质量
        } elseif (function_exists("imagegif")) {
            header("Content-Type: image/gif");
            imagegif($img);
        } elseif (function_exists("imagepng")) {
            header("Content-Type: image/x-png");
            imagepng($img);
        }
    }

    public function getCode(): string
    {
        return $this->code;
    }

    private function randString($length): string
    {
        //without symbols (o=0, 1=l, i=j, t=f)
        $allowed_symbols = "23456789abcdegikpqsvxyz";

        while (true) {
            $str = '';
            for ($i = 0; $i < $length; $i++) {
                $str .= $allowed_symbols[mt_rand(0, strlen($allowed_symbols) - 1)];
            }

            if (!preg_match('/cp|cb|ck|c6|c9|rn|rm|mm|co|do|cl|db|qp|qb|dp|ww/', $str)) {
                break;
            }
        }

        return $str;
    }

    private function frand(): int
    {
        return (int)(mt_rand(0, 9999) / 10000);
    }

    private function drawLine(&$img, $width, $height): void
    {
        $line_number = 7;
        $color_from  = 100;

        for ($line = 0; $line < $line_number; ++$line) {
            $line_color = imagecolorallocate($img, mt_rand($color_from, 255), mt_rand($color_from, 255), mt_rand($color_from, 255));

            $x = ($width * (1 + $line) / ($line_number + 1));

            $x += (0.6 - $this->frand()) * $width / $line_number;

            $y = mt_rand($height * 0.1, $height * 0.9);

            $theta = ($this->frand() - 0.5) * M_PI * 0.7;
            $w     = $width;
            $len   = mt_rand($w * 0.4, $w * 0.7);
            $lwid  = mt_rand(0, 2);

            $k   = $this->frand() * 0.6 + 0.2;
            $k   = $k * $k * 0.5;
            $phi = $this->frand() * 6.28;

            $step = 0.9;
            $dx   = $step * cos($theta);
            $dy   = $step * sin($theta);
            $n    = $len / $step;
            $amp  = 1.5 * $this->frand() / ($k + 5.0 / $len);
            $x0   = $x - 0.5 * $len * cos($theta);
            $y0   = $y - 0.5 * $len * sin($theta);

            for ($i = 0; $i < $n; ++$i) {
                $x = (int)($x0 + $i * $dx + $amp * $dy * sin($k * $i * $step + $phi));
                $y = (int)($y0 + $i * $dy - $amp * $dx * sin($k * $i * $step + $phi));

                imagefilledrectangle($img, $x, $y, $x + $lwid, $y + $lwid, $line_color);
            }
        }

        $allowed_symbols = "0123456789abcdefghijklmnopqrstuvwxyz";

        for ($i = 0; $i < 20; $i++) {
            //写入随机字串
            $char       = $allowed_symbols[mt_rand(0, strlen($allowed_symbols) - 1)];
            $line_color = imagecolorallocate($img, mt_rand($color_from, 255), mt_rand($color_from, 255), mt_rand($color_from, 255));
            imagechar($img, mt_rand(0, 4), mt_rand(0, $width), rand(0, $height), $char, $line_color);
        }
    }

    private function make(): void
    {
        $width  = $this->width;
        $height = $this->height;
        $length = $this->length;

        $alphabet              = "0123456789abcdefghijklmnopqrstuvwxyz";       # do not change without changing font files!
        $fluctuation_amplitude = $this->height / 10;                           //上下起伏
        $white_noise_density   = 1 / 10;                                       //$white_noise_density=0; // no white noise
        $black_noise_density   = 1 / 100;                                      //$black_noise_density=0; // no black noise

        $foreground_color = [
            mt_rand(0, 120),
            mt_rand(0, 120),
            mt_rand(0, 120),
        ];
        $background_color = [
            mt_rand(220, 255),
            mt_rand(220, 255),
            mt_rand(220, 255),
        ];

        $font_path = dirname(__FILE__) . '/fonts/';

        $fonts = [
            $font_path . 'font_1.png',
            $font_path . 'font_2.png',
            $font_path . 'font_3.png',
        ];

        $alphabet_length = strlen($alphabet);

        do {
            $this->code = $this->randString($length);

            $font_file = $fonts[mt_rand(0, count($fonts) - 1)];

            $font = imagecreatefrompng($font_file);
            imagealphablending($font, true);
            $fontfile_width  = imagesx($font);
            $fontfile_height = imagesy($font) - 1;

            $font_metrics   = [];
            $symbol         = 0;
            $reading_symbol = false;

            // loading font
            for ($i = 0; $i < $fontfile_width && $symbol < $alphabet_length; $i++) {
                $transparent = (imagecolorat($font, $i, 0) >> 24) == 127;
                if (!$reading_symbol && !$transparent) {
                    $font_metrics[$alphabet[$symbol]] = ['start' => $i];
                    $reading_symbol                   = true;
                    continue;
                }

                if ($reading_symbol && $transparent) {
                    $font_metrics[$alphabet[$symbol]]['end'] = $i;
                    $reading_symbol                          = false;
                    $symbol++;
                    continue;
                }
            }

            $img = imagecreatetruecolor($width, $height);
            imagealphablending($img, true);
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, $white);
            $x   = 1;
            $odd = mt_rand(0, 1);
            if ($odd == 0) {
                $odd = -1;
            }
            for ($i = 0; $i < $length; $i++) {
                $m = $font_metrics[$this->code[$i]];

                $y = (int)((($i % 2) * $fluctuation_amplitude - $fluctuation_amplitude / 2) * $odd + mt_rand(-round($fluctuation_amplitude / 3), round($fluctuation_amplitude / 3)) + ($height - $fontfile_height) / 2);

                $shift = 1;

                imagecopy($img, $font, $x - $shift, $y, $m['start'], 1, $m['end'] - $m['start'], $fontfile_height);

                $x += $m['end'] - $m['start'] - $shift;
            }
        } while ($x >= $width - 10); // while not fit in canvas

        //noise
        $white = imagecolorallocate($font, 255, 255, 255);
        $black = imagecolorallocate($font, 0, 0, 0);

        for ($i = 0; $i < (($height - 30) * $x) * $white_noise_density; $i++) {
            imagesetpixel($img, mt_rand(0, $x - 1), mt_rand(10, $height - 15), $white);
        }
        for ($i = 0; $i < (($height - 30) * $x) * $black_noise_density; $i++) {
            imagesetpixel($img, mt_rand(0, $x - 1), mt_rand(10, $height - 15), $black);
        }

        $center = $x / 2;
        $center = (int)$center;

        $img2       = imagecreatetruecolor($width, $height);
        $foreground = imagecolorallocate($img2, $foreground_color[0], $foreground_color[1], $foreground_color[2]);
        $background = imagecolorallocate($img2, $background_color[0], $background_color[1], $background_color[2]);
        imagefilledrectangle($img2, 0, 0, $width - 1, $height - 1, $background);
        imagefilledrectangle($img2, 0, $height, $width - 1, $height + 12, $foreground);
        $this->drawLine($img2, $width, $height);

        // periods
        $rand1 = (int)(mt_rand(750000, 1200000) / 10000000);
        $rand2 = (int)(mt_rand(750000, 1200000) / 10000000);
        $rand3 = (int)(mt_rand(750000, 1200000) / 10000000);
        $rand4 = (int)(mt_rand(750000, 1200000) / 10000000);
        // phases
        $rand5 = (int)(mt_rand(0, 31415926) / 10000000);
        $rand6 = (int)(mt_rand(0, 31415926) / 10000000);
        $rand7 = (int)(mt_rand(0, 31415926) / 10000000);
        $rand8 = (int)(mt_rand(0, 31415926) / 10000000);
        // amplitudes
        $rand9  = (int)(mt_rand(330, 420) / 110);
        $rand10 = (int)(mt_rand(330, 450) / 100);

        //wave distortion
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $sx = $x + (sin($x * $rand1 + $rand5) + sin($y * $rand3 + $rand6)) * $rand9 - $width / 2 + $center + 1;
                $sy = $y + (sin($x * $rand2 + $rand7) + sin($y * $rand4 + $rand8)) * $rand10;

                $sx = (int)$sx;
                $sy = (int)$sy;

                if ($sx < 0 || $sy < 0 || $sx >= $width - 1 || $sy >= $height - 1) {
                    continue;
                } else {
                    $color    = imagecolorat($img, $sx, $sy) & 0xFF;
                    $color_x  = imagecolorat($img, $sx + 1, $sy) & 0xFF;
                    $color_y  = imagecolorat($img, $sx, $sy + 1) & 0xFF;
                    $color_xy = imagecolorat($img, $sx + 1, $sy + 1) & 0xFF;
                }

                if ($color == 255 && $color_x == 255 && $color_y == 255 && $color_xy == 255) {
                    continue;
                } elseif ($color == 0 && $color_x == 0 && $color_y == 0 && $color_xy == 0) {
                    $newred   = $foreground_color[0];
                    $newgreen = $foreground_color[1];
                    $newblue  = $foreground_color[2];
                } else {
                    $frsx  = $sx - floor($sx);
                    $frsy  = $sy - floor($sy);
                    $frsx1 = 1 - $frsx;
                    $frsy1 = 1 - $frsy;

                    $newcolor = (int)($color * $frsx1 * $frsy1 + $color_x * $frsx * $frsy1 + $color_y * $frsx1 * $frsy + $color_xy * $frsx * $frsy);
                    if ($newcolor > 255) {
                        $newcolor = 255;
                    }
                    $newcolor  = $newcolor / 255;
                    $newcolor0 = 1 - $newcolor;

                    $newred   = (int)($newcolor0 * $foreground_color[0] + $newcolor * $background_color[0]);
                    $newgreen = (int)($newcolor0 * $foreground_color[1] + $newcolor * $background_color[1]);
                    $newblue  = (int)($newcolor0 * $foreground_color[2] + $newcolor * $background_color[2]);
                }

                imagesetpixel($img2, $x, $y, imagecolorallocate($img2, $newred, $newgreen, $newblue));
            }
        }

        $this->img = $img2;
    }
}
