<?php

namespace App\Filament\Resources\RekeningPerusahaans\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RekeningPerusahaanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('pemilik_rekening'),
                TextEntry::make('nama_bank'),
                TextEntry::make('no_rekening'),
                TextEntry::make('atas_nama'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
