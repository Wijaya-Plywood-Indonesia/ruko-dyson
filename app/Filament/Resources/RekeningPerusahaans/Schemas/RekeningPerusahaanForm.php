<?php

namespace App\Filament\Resources\RekeningPerusahaans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RekeningPerusahaanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('pemilik_rekening')
                    ->default(null),
                TextInput::make('nama_bank')
                    ->default(null),
                TextInput::make('no_rekening')
                    ->default(null),
                TextInput::make('atas_nama')
                    ->default(null),
            ]);
    }
}
