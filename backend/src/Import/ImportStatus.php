<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Issue d'une tentative d'import d'opportunité (voir App\Import\ImportResult).
 */
enum ImportStatus: string
{
    /** Une nouvelle Opportunity a été créée et persistée. */
    case CREATED = 'created';

    /** Une Opportunity équivalente existait déjà : rien n'a été créé. */
    case DUPLICATE = 'duplicate';

    /** L'entité mappée n'a pas passé la validation : rien n'a été persisté. */
    case VALIDATION_ERROR = 'validation_error';

    /** La source est reconnue mais l'importeur n'implémente pas encore l'import réel (ex. LinkedIn/Malt). */
    case NOT_IMPLEMENTED = 'not_implemented';
}
