<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Services\BlankPdfService;
use Tests\TestCase;

class BlankPdfServiceTest extends TestCase
{
    public function test_blank_in_times_new_roman_renders_cyrillic_instead_of_question_marks(): void
    {
        $body = '<p><span style="font-family:\'Times New Roman\', serif;">Заявка кредитного комитета</span></p>';

        $pdf = (new BlankPdfService)->render(new Document, $body);

        // Базовый Times знает только cp1252: попади текст в него — в PDF легли бы «?».
        $this->assertStringNotContainsString('/Times-Roman', $pdf);
    }
}
