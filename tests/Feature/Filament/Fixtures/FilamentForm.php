<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Fixtures;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Component;

final class FilamentForm extends Component implements HasForms
{
    use InteractsWithForms;

    public static function make(): self
    {
        return new self;
    }
}
