<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\MessageTemplate;
use App\Repository\MessageTemplateRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Refuse la création ou la modification d'un MessageTemplate dont le titre
 * existe déjà (comparaison insensible à la casse et aux espaces en début et
 * fin, voir MessageTemplateRepository::findOneByTitleIgnoreCase()), puis
 * délègue au processor de persistance Doctrine par défaut d'API Platform.
 *
 * Même principe que App\State\CompanyUniqueNameProcessor : le refus prend la
 * forme d'une ValidationException, traduite par API Platform en 422 avec une
 * violation sur `title`.
 */
final class MessageTemplateUniqueTitleProcessor implements ProcessorInterface
{
    public const DUPLICATE_TITLE_MESSAGE = 'Un message porte déjà ce titre.';

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $decorated,
        private readonly MessageTemplateRepository $repository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof MessageTemplate) {
            $duplicate = $this->repository->findOneByTitleIgnoreCase($data->getTitle(), $this->resolveCurrentId($data, $uriVariables, $context));

            if (null !== $duplicate) {
                throw new ValidationException(new ConstraintViolationList([
                    new ConstraintViolation(
                        self::DUPLICATE_TITLE_MESSAGE,
                        self::DUPLICATE_TITLE_MESSAGE,
                        [],
                        $data,
                        'title',
                        $data->getTitle(),
                    ),
                ]));
            }
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Identifiant du modèle en cours de modification, à exclure de la
     * recherche de doublon. Sur un PUT, API Platform 4 désérialise la
     * ressource dans un objet neuf (sans id) : l'id est alors pris dans
     * `previous_data` ou dans les variables d'URI.
     *
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    private function resolveCurrentId(MessageTemplate $template, array $uriVariables, array $context): ?int
    {
        if (null !== $template->getId()) {
            return $template->getId();
        }

        $previous = $context['previous_data'] ?? null;
        if ($previous instanceof MessageTemplate && null !== $previous->getId()) {
            return $previous->getId();
        }

        return isset($uriVariables['id']) ? (int) $uriVariables['id'] : null;
    }
}
