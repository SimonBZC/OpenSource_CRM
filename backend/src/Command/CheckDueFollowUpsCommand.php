<?php

declare(strict_types=1);

namespace App\Command;

use App\Automation\DomainEventBus;
use App\Automation\Event\FollowUpDue;
use App\Enum\FollowUpStatus;
use App\Repository\FollowUpRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Scanne les relances (FollowUp) échues et émet un événement FollowUpDue
 * pour chacune via App\Automation\DomainEventBus.
 *
 * "Échue" = status TODO et scheduledAt <= fin de journée locale
 * d'aujourd'hui. Les relances EN RETARD (scheduledAt dans le passé, avant
 * aujourd'hui) sont volontairement INCLUSES : une relance manquée reste une
 * relance à traiter, et l'ignorer serait plus dommageable pour
 * l'utilisateur qu'une notification en double si la commande est relancée
 * plusieurs fois (la notification est un simple log, sans effet de bord
 * destructif).
 *
 * Aucun scheduler/cron n'est configuré par cette tâche : cette commande est
 * conçue pour être appelée par un ordonnanceur externe (cron système,
 * Symfony Scheduler...) mis en place ultérieurement. Elle ne s'auto-planifie
 * pas elle-même.
 */
#[AsCommand(
    name: 'app:automation:check-due-followups',
    description: "Émet un événement FollowUpDue pour chaque relance TODO échue (retards inclus).",
)]
final class CheckDueFollowUpsCommand extends Command
{
    public function __construct(
        private readonly FollowUpRepository $followUpRepository,
        private readonly DomainEventBus $eventBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                "N'émet aucun événement FollowUpDue, affiche seulement le nombre de relances qui seraient traitées.",
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        // Fin de journée locale d'aujourd'hui (23:59:59) : inclut donc
        // aujourd'hui et tout le passé (retards).
        $endOfToday = (new \DateTimeImmutable('today'))
            ->modify('+1 day')
            ->modify('-1 second');

        $dueFollowUps = $this->followUpRepository->findDueFollowUps($endOfToday, FollowUpStatus::TODO);

        if (!$dryRun) {
            foreach ($dueFollowUps as $followUp) {
                $this->eventBus->dispatch(new FollowUpDue($followUp));
            }
        }

        $io->success(sprintf(
            '%d relance(s) échue(s) %s.',
            \count($dueFollowUps),
            $dryRun ? 'détectée(s) (dry-run : aucun événement émis)' : 'traitée(s)',
        ));

        return Command::SUCCESS;
    }
}
