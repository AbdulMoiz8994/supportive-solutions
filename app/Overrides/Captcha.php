<?php

namespace App\Overrides;

use Mews\Captcha\Captcha as MewsCaptcha;

class Captcha extends MewsCaptcha
{
    /**
     * Writing captcha text
     *
     * @return void
     */
    protected function text(): void
    {
        // Fix: Explicitly cast to int to avoid PHP 8.1+ deprecation warnings
        $marginTop = (int)($this->image->height() / $this->length);
        if ($this->marginTop !== 0) {
            $marginTop = $this->marginTop;
        }

        $text = $this->text;
        if (is_string($text)) {
            $text = str_split($text);
        }

        foreach ($text as $key => $char) {
            // Fix: Explicitly cast to int to avoid PHP 8.1+ deprecation warnings
            $marginLeft = (int)($this->textLeftPadding + ($key * ($this->image->width() - $this->textLeftPadding) / $this->length));

            $this->image->text($char, $marginLeft, $marginTop, function ($font) {
                /* @var \Intervention\Image\Gd\Font $font */
                $font->file($this->font());
                $font->size($this->fontSize());
                $font->color($this->fontColor());
                $font->align('left');
                $font->valign('top');
                $font->angle($this->angle());
            });
        }
    }
}
