<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Alert;
use App\Entity\AlertRule;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\FollowUp;
use App\Entity\Interaction;
use App\Entity\MessageTemplate;
use App\Entity\Opportunity;
use App\Enum\AlertPriority;
use App\Enum\AlertRuleType;
use App\Enum\AlertStatus;
use App\Enum\FollowUpStatus;
use App\Enum\InteractionType;
use App\Enum\OpportunitySource;
use App\Enum\OpportunityStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de données réaliste pour la prospection freelance tech en local.
 * Toutes les dates sont calculées relativement à "maintenant" pour rester cohérentes dans le temps.
 */
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable();

        // ------------------------------------------------------------------
        // Companies
        // ------------------------------------------------------------------

        $synapse = (new Company())
            ->setName('Synapse Consulting')
            ->setWebsite('https://synapse-consulting.fr')
            ->setSector('ESN / Conseil IT')
            ->setLocation('Paris, France')
            ->setDescription("ESN de taille intermédiaire spécialisée dans les missions d'expertise technique pour de grands comptes.")
            ->setNotes('Passe régulièrement par des freelances pour ses missions PHP/Symfony.')
            ->setSource(OpportunitySource::LINKEDIN);

        $lumo = (new Company())
            ->setName('Lumo Énergie')
            ->setWebsite('https://lumo-energie.io')
            ->setSector('Scale-up / Énergie verte')
            ->setLocation('Lyon, France')
            ->setDescription('Scale-up dans la gestion intelligente de la consommation énergétique, forte croissance produit.')
            ->setNotes('Equipe tech en pleine expansion, recrute aussi des freelances en renfort.')
            ->setSource(OpportunitySource::MALT);

        $pixelSens = (new Company())
            ->setName('Pixel & Sens')
            ->setWebsite('https://pixeletsens.agency')
            ->setSector('Agence digitale')
            ->setLocation('Nantes, France')
            ->setDescription('Agence web spécialisée dans les sites vitrines et e-commerce pour PME.')
            ->setNotes(null)
            ->setSource(OpportunitySource::WEBSITE);

        $banquePlus = (new Company())
            ->setName('BanquePlus')
            ->setWebsite('https://banqueplus.fr')
            ->setSector('Banque / Grand compte')
            ->setLocation('La Défense, Paris')
            ->setDescription('Grand groupe bancaire, appels d\'offres via ESN partenaires principalement.')
            ->setNotes("Process d'onboarding long, prévoir plusieurs semaines avant démarrage.")
            ->setSource(OpportunitySource::RECOMMENDATION);

        $neoHealth = (new Company())
            ->setName('NeoHealth')
            ->setWebsite('https://neohealth.co')
            ->setSector('Startup / Santé numérique')
            ->setLocation('Remote / Bordeaux')
            ->setDescription('Startup en Series A développant une plateforme de suivi patient à distance.')
            ->setNotes('Équipe 100% remote, stack moderne, décision rapide.')
            ->setSource(OpportunitySource::EMAIL);

        $techTalent = (new Company())
            ->setName('TechTalent Partners')
            ->setWebsite('https://techtalent-partners.com')
            ->setSector('Cabinet de recrutement tech')
            ->setLocation('Paris, France')
            ->setDescription("Cabinet spécialisé dans le placement de freelances tech auprès de clients variés.")
            ->setNotes(null)
            ->setSource(OpportunitySource::MANUAL);

        foreach ([$synapse, $lumo, $pixelSens, $banquePlus, $neoHealth, $techTalent] as $company) {
            $manager->persist($company);
        }

        // ------------------------------------------------------------------
        // Contacts
        // ------------------------------------------------------------------

        $marie = (new Contact())
            ->setFirstName('Marie')
            ->setLastName('Dubreuil')
            ->setJobTitle('CTO')
            ->setEmail('marie.dubreuil@lumo-energie.io')
            ->setPhone('06 12 34 56 78')
            ->setLinkedin('https://linkedin.com/in/marie-dubreuil')
            ->setCompany($lumo)
            ->setNotes("Décisionnaire technique, réactive par email.");

        $thomas = (new Contact())
            ->setFirstName('Thomas')
            ->setLastName('Lefevre')
            ->setJobTitle('Talent Acquisition Manager')
            ->setEmail('thomas.lefevre@synapse-consulting.fr')
            ->setPhone('01 45 67 89 10')
            ->setLinkedin('https://linkedin.com/in/thomas-lefevre')
            ->setCompany($synapse)
            ->setNotes('Gère le sourcing freelance côté ESN.');

        $camille = (new Contact())
            ->setFirstName('Camille')
            ->setLastName('Nguyen')
            ->setJobTitle('Lead Developer')
            ->setEmail('camille.nguyen@pixeletsens.agency')
            ->setPhone('06 98 76 54 32')
            ->setLinkedin('https://linkedin.com/in/camille-nguyen')
            ->setCompany($pixelSens)
            ->setNotes(null);

        $julien = (new Contact())
            ->setFirstName('Julien')
            ->setLastName('Roche')
            ->setJobTitle('Directeur des Systèmes d\'Information')
            ->setEmail('julien.roche@banqueplus.fr')
            ->setPhone(null)
            ->setLinkedin('https://linkedin.com/in/julien-roche')
            ->setCompany($banquePlus)
            ->setNotes("Contact obtenu par recommandation, passe par des ESN référencées.");

        $sarah = (new Contact())
            ->setFirstName('Sarah')
            ->setLastName('Benali')
            ->setJobTitle('Head of Product')
            ->setEmail('sarah.benali@neohealth.co')
            ->setPhone('07 11 22 33 44')
            ->setLinkedin('https://linkedin.com/in/sarah-benali')
            ->setCompany($neoHealth)
            ->setNotes('Très active, cherche à staffer vite.');

        $nicolas = (new Contact())
            ->setFirstName('Nicolas')
            ->setLastName('Petit')
            ->setJobTitle('Recruteur Freelance')
            ->setEmail('nicolas.petit@techtalent-partners.com')
            ->setPhone('06 55 44 33 22')
            ->setLinkedin('https://linkedin.com/in/nicolas-petit')
            ->setCompany($techTalent)
            ->setNotes('Envoie régulièrement des opportunités par email.');

        $laure = (new Contact())
            ->setFirstName('Laure')
            ->setLastName('Simon')
            ->setJobTitle('Ingénieure Backend Senior')
            ->setEmail('laure.simon@synapse-consulting.fr')
            ->setPhone(null)
            ->setLinkedin('https://linkedin.com/in/laure-simon')
            ->setCompany($synapse)
            ->setNotes('Future collègue potentielle sur la mission BanquePlus.');

        $antoine = (new Contact())
            ->setFirstName('Antoine')
            ->setLastName('Girard')
            ->setJobTitle('CEO')
            ->setEmail('antoine.girard@neohealth.co')
            ->setPhone('06 21 43 65 87')
            ->setLinkedin('https://linkedin.com/in/antoine-girard')
            ->setCompany($neoHealth)
            ->setNotes(null);

        $elise = (new Contact())
            ->setFirstName('Élise')
            ->setLastName('Fontaine')
            ->setJobTitle('Office Manager')
            ->setEmail('elise.fontaine@pixeletsens.agency')
            ->setPhone('02 40 12 34 56')
            ->setLinkedin(null)
            ->setCompany($pixelSens)
            ->setNotes('Point de contact administratif.')
            // Contact inactif : masqué par défaut, mais encore lié à des relances et opportunités.
            ->setActive(false);

        foreach ([$marie, $thomas, $camille, $julien, $sarah, $nicolas, $laure, $antoine, $elise] as $contact) {
            $manager->persist($contact);
        }

        // ------------------------------------------------------------------
        // Opportunities
        // ------------------------------------------------------------------

        $oppLumoBackend = (new Opportunity())
            ->setTitle('Développeur Symfony senior — refonte back-office')
            ->setDescription("Refonte complète du back-office de gestion des contrats énergétiques en Symfony 6 / API Platform.")
            ->setCompany($lumo)
            ->setContact($marie)
            ->setSource(OpportunitySource::MALT)
            ->setStatus(OpportunityStatus::INTERVIEW)
            ->setDailyRate(650)
            ->setRemote(true)
            ->setLocation('Lyon (hybride)')
            ->setDuration('6 mois')
            ->setStartDate($now->modify('+3 weeks'))
            ->setTechnologies(['PHP', 'Symfony', 'API Platform', 'PostgreSQL', 'Docker'])
            ->setScore(88)
            ->setScoreReason('Bonne adéquation stack, TJM cohérent, client réactif.')
            ->setNotes('Entretien technique prévu avec Marie et son équipe.');

        $oppSynapseBanque = (new Opportunity())
            ->setTitle('Expert PHP pour mission BanquePlus (via ESN)')
            ->setDescription('Mission longue durée chez BanquePlus, staffée via Synapse Consulting.')
            ->setCompany($synapse)
            ->setContact($thomas)
            ->setSource(OpportunitySource::LINKEDIN)
            ->setStatus(OpportunityStatus::WAITING)
            ->setDailyRate(580)
            ->setRemote(false)
            ->setLocation('La Défense, Paris')
            ->setDuration('12 mois renouvelable')
            ->setStartDate($now->modify('+6 weeks'))
            ->setTechnologies(['PHP', 'Symfony', 'Oracle', 'Jenkins'])
            ->setScore(65)
            ->setScoreReason("Process de validation long côté banque, dossier envoyé à BanquePlus.")
            ->setNotes("En attente de retour du client final.");

        $oppPixelEcommerce = (new Opportunity())
            ->setTitle('Développeur e-commerce PrestaShop/Symfony')
            ->setDescription("Mission courte pour la refonte d'une boutique e-commerce.")
            ->setCompany($pixelSens)
            ->setContact($camille)
            ->setSource(OpportunitySource::WEBSITE)
            ->setStatus(OpportunityStatus::TO_REVIEW)
            ->setDailyRate(450)
            ->setRemote(true)
            ->setLocation('Remote')
            ->setDuration('2 mois')
            ->setStartDate(null)
            ->setTechnologies(['PHP', 'Symfony', 'MySQL'])
            ->setScore(null)
            ->setScoreReason(null)
            ->setNotes("À qualifier, TJM un peu bas.");

        $oppBanqueDirect = (new Opportunity())
            ->setTitle('Architecte technique — modernisation SI')
            ->setDescription('Mission d\'architecture pour la modernisation du système d\'information interne.')
            ->setCompany($banquePlus)
            ->setContact($julien)
            ->setSource(OpportunitySource::RECOMMENDATION)
            ->setStatus(OpportunityStatus::NEW)
            ->setDailyRate(750)
            ->setRemote(false)
            ->setLocation('La Défense, Paris')
            ->setDuration('8 mois')
            ->setStartDate(null)
            ->setTechnologies(['Symfony', 'API Platform', 'PostgreSQL', 'Kubernetes'])
            ->setScore(null)
            ->setScoreReason(null)
            ->setNotes('Contact chaud via recommandation, à contacter rapidement.');

        $oppNeoHealthFullstack = (new Opportunity())
            ->setTitle('Développeur fullstack Symfony / Next.js')
            ->setDescription("Renfort de l'équipe produit pour accélérer le développement de la plateforme patient.")
            ->setCompany($neoHealth)
            ->setContact($sarah)
            ->setSource(OpportunitySource::EMAIL)
            ->setStatus(OpportunityStatus::CONTACTED)
            ->setDailyRate(600)
            ->setRemote(true)
            ->setLocation('Remote')
            ->setDuration('4 mois')
            ->setStartDate($now->modify('+2 weeks'))
            ->setTechnologies(['PHP', 'Symfony', 'API Platform', 'Next.js', 'React', 'Docker'])
            ->setScore(82)
            ->setScoreReason('Stack parfaitement alignée, équipe remote-first.')
            ->setNotes('Premier échange très positif.');

        $oppTechTalentApi = (new Opportunity())
            ->setTitle('Développeur API Platform pour client fintech')
            ->setDescription('Mission chez un client fintech du cabinet, via TechTalent Partners.')
            ->setCompany($techTalent)
            ->setContact($nicolas)
            ->setSource(OpportunitySource::MANUAL)
            ->setStatus(OpportunityStatus::FOLLOW_UP)
            ->setDailyRate(620)
            ->setRemote(true)
            ->setLocation('Remote')
            ->setDuration('3 mois')
            ->setStartDate(null)
            ->setTechnologies(['PHP', 'API Platform', 'PostgreSQL'])
            ->setScore(70)
            ->setScoreReason('Bon fit technique, en attente de détails sur le client final.')
            ->setNotes("Relance prévue auprès de Nicolas.");

        $oppLumoData = (new Opportunity())
            ->setTitle('Ingénieur data — pipeline de consommation énergétique')
            ->setDescription('Mise en place de pipelines de traitement de données de consommation en temps réel.')
            ->setCompany($lumo)
            ->setContact($marie)
            ->setSource(OpportunitySource::MALT)
            ->setStatus(OpportunityStatus::WON)
            ->setDailyRate(700)
            ->setRemote(true)
            ->setLocation('Lyon (hybride)')
            ->setDuration('9 mois')
            ->setStartDate($now->modify('-1 month'))
            ->setTechnologies(['Python', 'PostgreSQL', 'Docker', 'Airflow'])
            ->setScore(91)
            ->setScoreReason('Mission signée, excellent client.')
            ->setNotes('Mission en cours, TJM validé à 650€.');

        $oppPixelRefonte = (new Opportunity())
            ->setTitle('Développeur front React — refonte vitrine')
            ->setDescription("Refonte de plusieurs sites vitrines clients en React.")
            ->setCompany($pixelSens)
            ->setContact($elise)
            ->setSource(OpportunitySource::WEBSITE)
            ->setStatus(OpportunityStatus::LOST)
            ->setDailyRate(420)
            ->setRemote(true)
            ->setLocation('Remote')
            ->setDuration('1 mois')
            ->setStartDate(null)
            ->setTechnologies(['React', 'TypeScript'])
            ->setScore(40)
            ->setScoreReason('TJM trop bas par rapport au marché, décliné.')
            ->setNotes('Opportunité déclinée par manque de budget côté client.');

        $oppSynapseLegacy = (new Opportunity())
            ->setTitle('Développeur PHP — maintenance applicative')
            ->setDescription("Mission de maintenance sur une application legacy PHP7.")
            ->setCompany($synapse)
            ->setContact($laure)
            ->setSource(OpportunitySource::LINKEDIN)
            ->setStatus(OpportunityStatus::ARCHIVED)
            ->setDailyRate(480)
            ->setRemote(false)
            ->setLocation('Paris')
            ->setDuration('2 mois')
            ->setStartDate(null)
            ->setTechnologies(['PHP', 'Symfony 3'])
            ->setScore(30)
            ->setScoreReason('Stack trop ancienne, peu d\'intérêt technique.')
            ->setNotes('Archivée, ne pas relancer sauf changement de scope.');

        $oppNeoHealthMobile = (new Opportunity())
            ->setTitle('Développeur API pour application mobile patients')
            ->setDescription("Développement des endpoints API pour la nouvelle application mobile.")
            ->setCompany($neoHealth)
            ->setContact($antoine)
            ->setSource(OpportunitySource::EMAIL)
            ->setStatus(OpportunityStatus::NEW)
            ->setDailyRate(580)
            ->setRemote(true)
            ->setLocation('Remote')
            ->setDuration('5 mois')
            ->setStartDate(null)
            ->setTechnologies(['PHP', 'Symfony', 'API Platform'])
            ->setScore(null)
            ->setScoreReason(null)
            ->setNotes("Reçu par email, à qualifier.");

        $oppTechTalentDevops = (new Opportunity())
            ->setTitle('DevOps / Infra pour client e-santé')
            ->setDescription("Mission d'infrastructure et de mise en place CI/CD.")
            ->setCompany($techTalent)
            ->setContact($nicolas)
            ->setSource(OpportunitySource::MANUAL)
            ->setStatus(OpportunityStatus::CONTACTED)
            ->setDailyRate(650)
            ->setRemote(true)
            ->setLocation('Remote')
            ->setDuration('4 mois')
            ->setStartDate($now->modify('+1 month'))
            ->setTechnologies(['Docker', 'Kubernetes', 'Terraform', 'GitLab CI'])
            ->setScore(75)
            ->setScoreReason('Hors coeur de cible mais TJM intéressant.')
            ->setNotes(null);

        $opportunities = [
            $oppLumoBackend, $oppSynapseBanque, $oppPixelEcommerce, $oppBanqueDirect,
            $oppNeoHealthFullstack, $oppTechTalentApi, $oppLumoData, $oppPixelRefonte,
            $oppSynapseLegacy, $oppNeoHealthMobile, $oppTechTalentDevops,
        ];

        foreach ($opportunities as $opportunity) {
            $manager->persist($opportunity);
        }

        // ------------------------------------------------------------------
        // Interactions
        // ------------------------------------------------------------------

        $interactions = [
            (new Interaction())
                ->setType(InteractionType::EMAIL)
                ->setOccurredAt($now->modify('-25 days'))
                ->setContent("Premier contact par email suite à l'annonce Malt.")
                ->setContact($marie)->setCompany($lumo)->setOpportunity($oppLumoBackend),
            (new Interaction())
                ->setType(InteractionType::PHONE)
                ->setOccurredAt($now->modify('-20 days'))
                ->setContent("Appel de cadrage avec Marie : besoins, budget, planning.")
                ->setContact($marie)->setCompany($lumo)->setOpportunity($oppLumoBackend),
            (new Interaction())
                ->setType(InteractionType::LINKEDIN)
                ->setOccurredAt($now->modify('-10 days'))
                ->setContent("Message LinkedIn confirmant l'entretien technique.")
                ->setContact($marie)->setCompany($lumo)->setOpportunity($oppLumoBackend),
            (new Interaction())
                ->setType(InteractionType::EMAIL)
                ->setOccurredAt($now->modify('-40 days'))
                ->setContent("Réponse à l'offre LinkedIn de la mission BanquePlus.")
                ->setContact($thomas)->setCompany($synapse)->setOpportunity($oppSynapseBanque),
            (new Interaction())
                ->setType(InteractionType::PHONE)
                ->setOccurredAt($now->modify('-30 days'))
                ->setContent("Échange téléphonique sur le process de sélection côté client final.")
                ->setContact($thomas)->setCompany($synapse)->setOpportunity($oppSynapseBanque),
            (new Interaction())
                ->setType(InteractionType::NOTE)
                ->setOccurredAt($now->modify('-2 days'))
                ->setContent('Dossier transmis à BanquePlus, en attente de retour.')
                ->setContact($thomas)->setCompany($synapse)->setOpportunity($oppSynapseBanque),
            (new Interaction())
                ->setType(InteractionType::EMAIL)
                ->setOccurredAt($now->modify('-5 days'))
                ->setContent("Demande de précisions sur le scope de la mission e-commerce.")
                ->setContact($camille)->setCompany($pixelSens)->setOpportunity($oppPixelEcommerce),
            (new Interaction())
                ->setType(InteractionType::LINKEDIN)
                ->setOccurredAt($now->modify('-3 days'))
                ->setContent("Prise de contact avec Julien Roche suite à la recommandation.")
                ->setContact($julien)->setCompany($banquePlus)->setOpportunity($oppBanqueDirect),
            (new Interaction())
                ->setType(InteractionType::EMAIL)
                ->setOccurredAt($now->modify('-8 days'))
                ->setContent("Réponse à l'annonce NeoHealth, envoi du CV et disponibilités.")
                ->setContact($sarah)->setCompany($neoHealth)->setOpportunity($oppNeoHealthFullstack),
            (new Interaction())
                ->setType(InteractionType::MEETING)
                ->setOccurredAt($now->modify('-4 days'))
                ->setContent('Visio de présentation mutuelle avec Sarah et Antoine.')
                ->setContact($sarah)->setCompany($neoHealth)->setOpportunity($oppNeoHealthFullstack),
            (new Interaction())
                ->setType(InteractionType::EMAIL)
                ->setOccurredAt($now->modify('-15 days'))
                ->setContent('Nicolas propose la mission fintech via TechTalent Partners.')
                ->setContact($nicolas)->setCompany($techTalent)->setOpportunity($oppTechTalentApi),
            (new Interaction())
                ->setType(InteractionType::PHONE)
                ->setOccurredAt($now->modify('-60 days'))
                ->setContent('Kick-off téléphonique avant démarrage de la mission data Lumo.')
                ->setContact($marie)->setCompany($lumo)->setOpportunity($oppLumoData),
            (new Interaction())
                ->setType(InteractionType::EMAIL)
                ->setOccurredAt($now->modify('-45 days'))
                ->setContent("Notification de refus de l'opportunité vitrine, budget insuffisant.")
                ->setContact($elise)->setCompany($pixelSens)->setOpportunity($oppPixelRefonte),
            (new Interaction())
                ->setType(InteractionType::NOTE)
                ->setOccurredAt($now->modify('-90 days'))
                ->setContent('Mission archivée après désistement, stack jugée trop ancienne.')
                ->setContact($laure)->setCompany($synapse)->setOpportunity($oppSynapseLegacy),
            (new Interaction())
                ->setType(InteractionType::EMAIL)
                ->setOccurredAt($now->modify('-1 day'))
                ->setContent("Nouvelle opportunité DevOps envoyée par Nicolas.")
                ->setContact($nicolas)->setCompany($techTalent)->setOpportunity($oppTechTalentDevops),
        ];

        foreach ($interactions as $interaction) {
            $manager->persist($interaction);
        }

        // lastInteractionAt cohérent avec les interactions ci-dessus
        $lumo->setLastInteractionAt($now->modify('-10 days'));
        $synapse->setLastInteractionAt($now->modify('-1 day'));
        $pixelSens->setLastInteractionAt($now->modify('-5 days'));
        $banquePlus->setLastInteractionAt($now->modify('-3 days'));
        $neoHealth->setLastInteractionAt($now->modify('-4 days'));
        $techTalent->setLastInteractionAt($now->modify('-1 day'));

        // ------------------------------------------------------------------
        // FollowUps
        // ------------------------------------------------------------------

        $followUps = [
            (new FollowUp())
                ->setTitle("Relancer Marie avant l'entretien technique")
                ->setDescription('Confirmer les modalités du prochain entretien technique.')
                ->setScheduledAt($now->modify('+3 days'))
                ->setStatus(FollowUpStatus::TODO)
                ->setCompany($lumo)->setContact($marie)->setOpportunity($oppLumoBackend),
            (new FollowUp())
                ->setTitle('Relancer Thomas sur le retour BanquePlus')
                ->setDescription('Demander un point d\'avancement sur le dossier transmis.')
                ->setScheduledAt($now->modify('-4 days'))
                ->setStatus(FollowUpStatus::TODO)
                ->setCompany($synapse)->setContact($thomas)->setOpportunity($oppSynapseBanque),
            (new FollowUp())
                ->setTitle('Relancer Camille sur le scope e-commerce')
                ->setDescription('Obtenir les précisions demandées pour qualifier le TJM.')
                ->setScheduledAt($now->modify('-2 days'))
                ->setStatus(FollowUpStatus::TODO)
                ->setCompany($pixelSens)->setContact($camille)->setOpportunity($oppPixelEcommerce),
            (new FollowUp())
                ->setTitle('Envoyer une proposition détaillée à Julien')
                ->setDescription("Rédiger et envoyer une proposition d'intervention pour l'architecture.")
                ->setScheduledAt($now->modify('+7 days'))
                ->setStatus(FollowUpStatus::TODO)
                ->setCompany($banquePlus)->setContact($julien)->setOpportunity($oppBanqueDirect),
            (new FollowUp())
                ->setTitle('Confirmer la date de démarrage avec Sarah')
                ->setDescription(null)
                ->setScheduledAt($now->modify('-15 days'))
                ->setStatus(FollowUpStatus::DONE)
                ->setCompletedAt($now->modify('-14 days'))
                ->setCompany($neoHealth)->setContact($sarah)->setOpportunity($oppNeoHealthFullstack),
            (new FollowUp())
                ->setTitle('Relancer Nicolas sur les détails du client fintech')
                ->setDescription('Obtenir le nom du client final et le contexte technique précis.')
                ->setScheduledAt($now->modify('-1 day'))
                ->setStatus(FollowUpStatus::TODO)
                ->setCompany($techTalent)->setContact($nicolas)->setOpportunity($oppTechTalentApi),
            (new FollowUp())
                ->setTitle('Point mensuel mission data Lumo')
                ->setDescription('Faire un point d\'avancement avec Marie sur la mission en cours.')
                ->setScheduledAt($now->modify('-30 days'))
                ->setStatus(FollowUpStatus::DONE)
                ->setCompletedAt($now->modify('-29 days'))
                ->setCompany($lumo)->setContact($marie)->setOpportunity($oppLumoData),
            (new FollowUp())
                ->setTitle('Relance vitrine Pixel & Sens')
                ->setDescription("Relance annulée suite au refus de l'opportunité.")
                ->setScheduledAt($now->modify('-40 days'))
                ->setStatus(FollowUpStatus::CANCELLED)
                ->setCompany($pixelSens)->setContact($elise)->setOpportunity($oppPixelRefonte),
            (new FollowUp())
                ->setTitle('Qualifier la mission mobile NeoHealth')
                ->setDescription('Poser des questions de cadrage sur le scope de la mission mobile.')
                ->setScheduledAt($now->modify('+5 days'))
                ->setStatus(FollowUpStatus::TODO)
                ->setCompany($neoHealth)->setContact($antoine)->setOpportunity($oppNeoHealthMobile),
            (new FollowUp())
                ->setTitle('Relancer Nicolas sur la mission DevOps')
                ->setDescription(null)
                ->setScheduledAt($now->modify('+2 days'))
                ->setStatus(FollowUpStatus::TODO)
                ->setCompany($techTalent)->setContact($nicolas)->setOpportunity($oppTechTalentDevops),
        ];

        foreach ($followUps as $followUp) {
            $manager->persist($followUp);
        }

        // ------------------------------------------------------------------
        // Alerts
        // ------------------------------------------------------------------

        $alerts = [
            (new Alert())
                ->setTitle('Préparer les slides de présentation technique')
                ->setDescription("Préparer un support pour l'entretien technique Lumo Énergie.")
                ->setDueDate($now->modify('+2 days'))
                ->setStatus(AlertStatus::IN_PROGRESS)
                ->setPriority(AlertPriority::HIGH)
                ->setOpportunity($oppLumoBackend)->setContact($marie),
            (new Alert())
                ->setTitle('Mettre à jour le CV avec les dernières missions')
                ->setDescription(null)
                ->setDueDate($now->modify('-3 days'))
                ->setStatus(AlertStatus::TODO)
                ->setPriority(AlertPriority::MEDIUM)
                ->setOpportunity(null)->setContact(null),
            (new Alert())
                ->setTitle('Envoyer le devis pour la mission BanquePlus')
                ->setDescription('Chiffrer et envoyer le devis détaillé pour la mission d\'architecture.')
                ->setDueDate($now->modify('-1 day'))
                ->setStatus(AlertStatus::TODO)
                ->setPriority(AlertPriority::URGENT)
                ->setOpportunity($oppBanqueDirect)->setContact($julien),
            (new Alert())
                ->setTitle('Préparer les questions de cadrage NeoHealth')
                ->setDescription(null)
                ->setDueDate($now->modify('+1 day'))
                ->setStatus(AlertStatus::TODO)
                ->setPriority(AlertPriority::MEDIUM)
                ->setOpportunity($oppNeoHealthFullstack)->setContact($sarah),
            (new Alert())
                ->setTitle('Signer le contrat de mission data Lumo')
                ->setDescription('Relire et signer le contrat-cadre avant démarrage.')
                ->setDueDate($now->modify('-32 days'))
                ->setStatus(AlertStatus::DONE)
                ->setPriority(AlertPriority::HIGH)
                ->setCompletedAt($now->modify('-31 days'))
                ->setOpportunity($oppLumoData)->setContact($marie),
            (new Alert())
                ->setTitle("Répondre à l'email de refus Pixel & Sens")
                ->setDescription('Accuser réception poliment et rester en contact pour de futures opportunités.')
                ->setDueDate($now->modify('-44 days'))
                ->setStatus(AlertStatus::DONE)
                ->setPriority(AlertPriority::LOW)
                ->setCompletedAt($now->modify('-43 days'))
                ->setOpportunity($oppPixelRefonte)->setContact($elise),
            (new Alert())
                ->setTitle('Relire les conditions générales TechTalent Partners')
                ->setDescription(null)
                ->setDueDate($now->modify('+4 days'))
                ->setStatus(AlertStatus::TODO)
                ->setPriority(AlertPriority::LOW)
                ->setOpportunity($oppTechTalentApi)->setContact($nicolas),
            (new Alert())
                ->setTitle('Faire le point sur les missions en attente')
                ->setDescription('Revue hebdomadaire du pipeline commercial.')
                ->setDueDate($now->modify('-6 days'))
                ->setStatus(AlertStatus::CANCELLED)
                ->setPriority(AlertPriority::MEDIUM)
                ->setOpportunity(null)->setContact(null),
            (new Alert())
                ->setTitle('Préparer proposition DevOps pour client e-santé')
                ->setDescription("Rédiger une proposition technique pour la mission d'infrastructure.")
                ->setDueDate($now->modify('+6 days'))
                ->setStatus(AlertStatus::IN_PROGRESS)
                ->setPriority(AlertPriority::HIGH)
                ->setOpportunity($oppTechTalentDevops)->setContact($nicolas),
            (new Alert())
                ->setTitle('Archiver les documents de la mission Synapse legacy')
                ->setDescription(null)
                ->setDueDate($now->modify('-80 days'))
                ->setStatus(AlertStatus::DONE)
                ->setPriority(AlertPriority::LOW)
                ->setCompletedAt($now->modify('-79 days'))
                ->setOpportunity($oppSynapseLegacy)->setContact($laure),
        ];

        foreach ($alerts as $alert) {
            $manager->persist($alert);
        }

        // ------------------------------------------------------------------
        // Messages pré-construits
        // ------------------------------------------------------------------

        $messageTemplates = [
            (new MessageTemplate())
                ->setTitle('Relance après candidature')
                ->setBody("Bonjour {%prenom%},\n\nJe me permets de revenir vers vous au sujet de ma candidature chez {%entreprise%}. Avez-vous pu avancer sur le sujet ?\n\nBien cordialement,\nSimon"),
            (new MessageTemplate())
                ->setTitle('Remerciement après entretien')
                ->setBody("Bonjour {%prenom%} {%nom%},\n\nMerci pour notre échange. Je reste à votre disposition pour toute question.\n\nBien cordialement,\nSimon"),
        ];

        foreach ($messageTemplates as $messageTemplate) {
            $manager->persist($messageTemplate);
        }

        // ------------------------------------------------------------------
        // Règles d'alerte configurables : mêmes réglages que la migration
        // (le chargement des fixtures vide la table alert_rule).
        // ------------------------------------------------------------------

        foreach (AlertRuleType::cases() as $ruleType) {
            $manager->persist(new AlertRule($ruleType));
        }

        $manager->flush();
    }
}
