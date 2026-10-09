<?php

namespace App\Enums;

enum ActivityType: string
{
    case Penataran = 'PENATARAN';
    case Kejuaraan = 'KEJUARAAN';
    case Rapat = 'RAPAT';
    case Pembinaan = 'PEMBINAAN';
    case Ujian = 'UJIAN';
    case Lainnya = 'LAINNYA';

    public function label(): string
    {
        return match ($this) {
            self::Penataran => 'Penataran',
            self::Kejuaraan => 'Kejuaraan',
            self::Rapat => 'Raker/Rapat',
            self::Pembinaan => 'Pembinaan',
            self::Ujian => 'Ujian',
            self::Lainnya => 'Lainnya',
        };
    }
}
