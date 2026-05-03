<?php

declare(strict_types=1);

namespace App\Enums\Workspace;

/**
 * Curated list of the most popular Google Fonts a workspace can pick as its
 * brand font. The string value matches the Google Fonts API family name so
 * the frontend can load it directly via the Fonts CSS endpoint.
 */
enum BrandFont: string
{
    case Inter = 'Inter';
    case Roboto = 'Roboto';
    case OpenSans = 'Open Sans';
    case NotoSans = 'Noto Sans';
    case Montserrat = 'Montserrat';
    case Poppins = 'Poppins';
    case Lato = 'Lato';
    case SourceSans3 = 'Source Sans 3';
    case RobotoCondensed = 'Roboto Condensed';
    case Oswald = 'Oswald';
    case Raleway = 'Raleway';
    case RobotoMono = 'Roboto Mono';
    case Nunito = 'Nunito';
    case Ubuntu = 'Ubuntu';
    case RobotoSlab = 'Roboto Slab';
    case Merriweather = 'Merriweather';
    case PlayfairDisplay = 'Playfair Display';
    case Rubik = 'Rubik';
    case PtSans = 'PT Sans';
    case WorkSans = 'Work Sans';
    case Mukta = 'Mukta';
    case NotoSerif = 'Noto Serif';
    case Lora = 'Lora';
    case Quicksand = 'Quicksand';
    case Kanit = 'Kanit';
    case Inconsolata = 'Inconsolata';
    case Heebo = 'Heebo';
    case DmSans = 'DM Sans';
    case Barlow = 'Barlow';
    case Karla = 'Karla';
    case Manrope = 'Manrope';
    case Mulish = 'Mulish';
    case BebasNeue = 'Bebas Neue';
    case Cabin = 'Cabin';
    case PublicSans = 'Public Sans';
    case FiraSans = 'Fira Sans';
    case Dosis = 'Dosis';
    case PlusJakartaSans = 'Plus Jakarta Sans';
    case Outfit = 'Outfit';
    case CormorantGaramond = 'Cormorant Garamond';
    case SourceSerif4 = 'Source Serif 4';
    case CrimsonPro = 'Crimson Pro';
    case LibreBaskerville = 'Libre Baskerville';
    case EbGaramond = 'EB Garamond';
    case Anton = 'Anton';
    case IbmPlexSans = 'IBM Plex Sans';
    case JetBrainsMono = 'JetBrains Mono';
    case Hind = 'Hind';
    case ArchivoNarrow = 'Archivo Narrow';
    case Archivo = 'Archivo';

    public const DEFAULT = self::Inter;

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $f) => $f->value, self::cases());
    }
}
