<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Publishing = 'publishing';
    case Published = 'published';
    case PartiallyPublished = 'partially_published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Scheduled => 'Agendado',
            self::Publishing => 'Publicando',
            self::Published => 'Publicado',
            self::PartiallyPublished => 'Parcialmente Publicado',
            self::Failed => 'Falhou',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'blue',
            self::Publishing => 'yellow',
            self::Published => 'green',
            self::PartiallyPublished => 'orange',
            self::Failed => 'red',
        };
    }
}
