<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Company;
use App\Repository\CompanyRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Refuse la création ou la modification d'une Company dont le nom existe déjà
 * (comparaison insensible à la casse et aux espaces en début et fin de nom,
 * voir CompanyRepository::findOneByNameIgnoreCase()), puis délègue au
 * processor de persistance Doctrine par défaut d'API Platform.
 *
 * Branché sur les opérations Post, Put et Patch de App\Entity\Company.
 * Volontairement PAS une contrainte d'entité (UniqueEntity ou contrainte de
 * classe) : le validateur autonome utilisé par tests/Entity/CompanyTest.php
 * n'a ni conteneur ni Doctrine, et UniqueEntity ne gère ni la casse ni le
 * trim. Le refus prend la forme d'une ValidationException : API Platform la
 * traduit en 422 avec une violation sur `name`, comme une erreur de
 * validation classique.
 */
final class CompanyUniqueNameProcessor implements ProcessorInterface
{
    public const DUPLICATE_NAME_MESSAGE = 'Une entreprise portant ce nom existe déjà.';

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $decorated,
        private readonly CompanyRepository $companyRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Company) {
            $duplicate = $this->companyRepository->findOneByNameIgnoreCase($data->getName(), $this->resolveCurrentId($data, $uriVariables, $context));

            if (null !== $duplicate) {
                throw new ValidationException(new ConstraintViolationList([
                    new ConstraintViolation(
                        self::DUPLICATE_NAME_MESSAGE,
                        self::DUPLICATE_NAME_MESSAGE,
                        [],
                        $data,
                        'name',
                        $data->getName(),
                    ),
                ]));
            }
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Identifiant de l'entreprise en cours de modification, à exclure de la
     * recherche de doublon. Sur un PUT, API Platform 4 désérialise la
     * ressource dans un objet neuf (sans id) avant de le fusionner : l'id est
     * alors pris dans `previous_data` ou dans les variables d'URI.
     *
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    private function resolveCurrentId(Company $company, array $uriVariables, array $context): ?int
    {
        if (null !== $company->getId()) {
            return $company->getId();
        }

        $previous = $context['previous_data'] ?? null;
        if ($previous instanceof Company && null !== $previous->getId()) {
            return $previous->getId();
        }

        return isset($uriVariables['id']) ? (int) $uriVariables['id'] : null;
    }
}
