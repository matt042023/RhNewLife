<?php

namespace App\DataFixtures;

use App\Entity\TemplateContrat;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TemplateContratFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Récupérer le premier admin user depuis la base
        $adminUser = $manager->getRepository(User::class)->findOneBy(['email' => 'admin@rhnewlife.fr']);

        if (!$adminUser) {
            // Si pas d'admin spécifique, prendre le premier user
            $adminUser = $manager->getRepository(User::class)->findOneBy([]);
        }

        // Template CDI Standard
        $templateCDI = new TemplateContrat();
        $templateCDI->setName('CDI - Contrat standard');
        $templateCDI->setDescription('Template de contrat CDI standard pour RH NewLife avec toutes les clauses de base');
        $templateCDI->setContentHtml($this->getTemplateCDIContent());
        $templateCDI->setActive(true);

        if ($adminUser) {
            $templateCDI->setCreatedBy($adminUser);
        }

        $manager->persist($templateCDI);

        // Template CDD Standard
        $templateCDD = new TemplateContrat();
        $templateCDD->setName('CDD - Contrat à durée déterminée');
        $templateCDD->setDescription('Template de contrat CDD standard avec motif et durée déterminée');
        $templateCDD->setContentHtml($this->getTemplateCDDContent());
        $templateCDD->setActive(true);

        if ($adminUser) {
            $templateCDD->setCreatedBy($adminUser);
        }

        $manager->persist($templateCDD);

        $manager->flush();
    }

    private function getTemplateCDIContent(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de Travail - {{ contract.type }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #000;
            padding: 40px 60px;
            max-width: 210mm;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 18pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .header .employer-info {
            font-size: 12pt;
            margin-top: 15px;
            line-height: 1.4;
        }

        .header .employer-name {
            font-weight: bold;
            font-size: 14pt;
            color: #34495e;
        }

        .section {
            margin: 30px 0;
            page-break-inside: avoid;
        }

        .section h2 {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
            color: #2c3e50;
            border-bottom: 2px solid #bdc3c7;
            padding-bottom: 5px;
        }

        .section p {
            margin: 10px 0;
            text-align: justify;
        }

        .info-box {
            background-color: #ecf0f1;
            padding: 15px;
            border-left: 4px solid #3498db;
            margin: 15px 0;
        }

        .info-box p {
            margin: 5px 0;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            min-width: 180px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        td {
            padding: 15px;
            vertical-align: top;
        }

        .signature-box {
            border: 1px solid #bdc3c7;
            min-height: 120px;
            padding: 15px;
            margin-top: 10px;
        }

        .signature-title {
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            margin-top: 60px;
            width: 200px;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #bdc3c7;
            font-size: 9pt;
            color: #7f8c8d;
            text-align: center;
        }

        .article-content {
            margin-left: 20px;
        }

        .important-notice {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .list-item {
            margin-left: 30px;
            margin-bottom: 8px;
        }

        @media print {
            body {
                padding: 20mm;
            }
            
            .section {
                page-break-inside: avoid;
            }
        }

        @page {
            margin: 20mm;
        }
    </style>
</head>
<body>
    <!-- En-tête du contrat -->
    <div class="header">
        <h1>CONTRAT DE TRAVAIL {{ contract.type == 'CDI' ? 'À DURÉE INDÉTERMINÉE' : 'À DURÉE DÉTERMINÉE' }}</h1>
        <div class="employer-info">
            <p class="employer-name">Association NEW LIFE</p>
            <p>Lieu de Vie et d'Accueil</p>
            <p>15 chemin des Gerbiers - 11120 ARGELIERS</p>
            <p>SIRET : 838 188 712 00015 - Code APE : 8790A</p>
            <p>R.N.A : W113003128</p>
            <p>Tél : 04.68.70.77.62</p>
        </div>
    </div>

    <!-- Parties contractantes -->
    <div class="section">
        <h2>ENTRE LES SOUSSIGNÉS :</h2>
        
        <div class="article-content">
            <p><strong>L'EMPLOYEUR :</strong></p>
            <div class="info-box">
                <p><span class="info-label">Dénomination :</span> Association NEW LIFE</p>
                <p><span class="info-label">Forme juridique :</span> Association loi 1901 à but non lucratif</p>
                <p><span class="info-label">SIRET :</span> 838 188 712 00015</p>
                <p><span class="info-label">Code APE :</span> 8790A</p>
                <p><span class="info-label">R.N.A :</span> W113003128</p>
                <p><span class="info-label">Adresse :</span> 15 chemin des Gerbiers, 11120 ARGELIERS</p>
                <p><span class="info-label">Téléphone :</span> 04.68.70.77.62</p>
                <p><span class="info-label">Représentée par :</span> M. Fernand ADJOVI, Directeur</p>
            </div>

            <p style="margin-top: 30px;"><strong>D'UNE PART,</strong></p>
            <p style="margin-top: 20px;"><strong>ET :</strong></p>

            <div class="info-box">
                <p><span class="info-label">Nom et Prénom :</span> <strong>{{ employee.fullName }}</strong></p>
                <p><span class="info-label">Matricule :</span> {{ employee.matricule }}</p>
                <p><span class="info-label">Adresse :</span> {{ employee.address }}</p>
                <p><span class="info-label">Email :</span> {{ employee.email }}</p>
                {% if employee.phone %}
                <p><span class="info-label">Téléphone :</span> {{ employee.phone }}</p>
                {% endif %}
                {% if employee.familyStatus %}
                <p><span class="info-label">Situation familiale :</span> {{ employee.familyStatus }}</p>
                {% endif %}
                {% if employee.children %}
                <p><span class="info-label">Nombre d'enfants :</span> {{ employee.children }}</p>
                {% endif %}
            </div>

            <p style="margin-top: 30px;"><strong>Ci-après dénommé(e) « LE SALARIÉ »</strong></p>
            <p style="margin-top: 20px;"><strong>D'AUTRE PART,</strong></p>
        </div>
    </div>

    <!-- Préambule -->
    <div class="section">
        <h2>PRÉAMBULE</h2>
        <div class="article-content">
            <p>L'Association NEW LIFE a pour objet l'organisation administrative et la gestion d'un lieu de vie - lieu d'accueil recevant des jeunes en difficultés et/ou en danger, dans le but de favoriser leur réinsertion sociale et/ou familiale.</p>
            
            {% if contract.type == 'CDD' %}
            <p style="margin-top: 15px;">Le présent contrat est conclu en application de l'article L. 1242-2 du Code du travail.</p>
            {% endif %}
        </div>
    </div>

    <!-- IL A ÉTÉ CONVENU CE QUI SUIT -->
    <div class="section" style="text-align: center; margin: 40px 0;">
        <p style="font-size: 13pt; font-weight: bold;">IL A ÉTÉ CONVENU ET ARRÊTÉ CE QUI SUIT :</p>
    </div>

    <!-- Article 1 : Engagement -->
    <div class="section">
        <h2>ARTICLE 1 - ENGAGEMENT</h2>
        <div class="article-content">
            <p>L'Employeur engage le Salarié qui accepte, aux clauses et conditions suivantes :</p>
            
            <div class="info-box" style="margin-top: 15px;">
                <p><span class="info-label">Poste occupé :</span> <strong>{{ employee.position }}</strong></p>
                {% if employee.structure %}
                <p><span class="info-label">Structure d'affectation :</span> {{ employee.structure }}</p>
                {% endif %}
                {% if contract.villa %}
                <p><span class="info-label">Villa :</span> {{ contract.villa }}</p>
                {% endif %}
                <p><span class="info-label">Type de contrat :</span> <strong>{{ contract.type }}</strong></p>
                <p><span class="info-label">Date de début :</span> <strong>{{ contract.startDate|date('d/m/Y') }}</strong></p>
                {% if contract.type == 'CDD' and contract.endDate %}
                <p><span class="info-label">Date de fin :</span> <strong>{{ contract.endDate|date('d/m/Y') }}</strong></p>
                {% endif %}
            </div>

            <p style="margin-top: 15px;">Le Salarié exercera ses fonctions sous l'autorité hiérarchique et conformément aux directives qui lui seront données par la Direction.</p>
        </div>
    </div>

    <!-- Article 2 : Fonctions et Missions -->
    <div class="section">
        <h2>ARTICLE 2 - FONCTIONS ET MISSIONS</h2>
        <div class="article-content">
            <p>Le Salarié aura pour missions principales celles correspondant au poste de <strong>{{ employee.position }}</strong>, notamment :</p>
            
            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">Assurer l'accueil et l'accompagnement des jeunes au quotidien</li>
                <li class="list-item">Participer à la mise en œuvre du projet éducatif de l'établissement</li>
                <li class="list-item">Contribuer à la réinsertion sociale et/ou familiale des jeunes accueillis</li>
                <li class="list-item">Travailler en équipe pluridisciplinaire</li>
                <li class="list-item">Participer aux réunions d'équipe et aux temps institutionnels</li>
                <li class="list-item">Assurer la sécurité physique et affective des jeunes</li>
                <li class="list-item">Rédiger les écrits professionnels nécessaires au suivi des jeunes</li>
            </ul>
            
            <p style="margin-top: 15px;">Le Salarié s'engage à accomplir toutes les tâches qui lui seront confiées dans le cadre de ses fonctions et à respecter les procédures internes de l'établissement.</p>
        </div>
    </div>

    <!-- Article 3 : Lieu de travail -->
    <div class="section">
        <h2>ARTICLE 3 - LIEU DE TRAVAIL</h2>
        <div class="article-content">
            <div class="info-box">
                <p><span class="info-label">Lieu principal :</span> 15 chemin des Gerbiers, 11120 ARGELIERS</p>
                {% if contract.villa %}
                <p><span class="info-label">Villa affectée :</span> {{ contract.villa }}</p>
                {% endif %}
            </div>
            
            <p style="margin-top: 15px;">Le Salarié pourra être amené à effectuer des déplacements ponctuels dans le cadre de ses missions (accompagnements extérieurs, transports des jeunes, etc.), en accord avec l'Employeur.</p>
        </div>
    </div>

    <!-- Article 4 : Durée du travail -->
    <div class="section">
        <h2>ARTICLE 4 - DURÉE DU TRAVAIL</h2>
        <div class="article-content">
            <div class="info-box">
                <p><span class="info-label">Durée hebdomadaire :</span> <strong>{{ contract.weeklyHours }} heures</strong></p>
                {% if contract.activityRate and contract.activityRate < 1 %}
                <p><span class="info-label">Taux d'activité :</span> {{ (contract.activityRate * 100)|number_format(0) }}%</p>
                <p><span class="info-label">Équivalent temps plein :</span> {{ (contract.activityRate * 35)|number_format(2) }} heures/semaine</p>
                {% endif %}
                {% if contract.workingDaysFormatted %}
                <p><span class="info-label">Jours travaillés :</span> {{ contract.workingDaysFormatted }}</p>
                {% endif %}
            </div>

            <p style="margin-top: 15px;">La durée légale du travail est fixée à 35 heures par semaine. Les horaires de travail pourront être répartis selon les nécessités du service et l'organisation de l'établissement.</p>

            <div class="important-notice">
                <p><strong>⚠ SPÉCIFICITÉS DU LIEU DE VIE</strong></p>
                <p>En raison de la nature de l'activité (accueil 24h/24 de jeunes en difficultés), le Salarié est susceptible d'effectuer :</p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li class="list-item">Du travail en soirée et/ou de nuit</li>
                    <li class="list-item">Du travail le week-end et jours fériés selon planning</li>
                    <li class="list-item">Des astreintes selon les besoins du service</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Article 5 : Période d'essai -->
    <div class="section">
        <h2>ARTICLE 5 - PÉRIODE D'ESSAI</h2>
        <div class="article-content">
            {% if contract.essaiEndDate %}
            <p>Le présent contrat est conclu sous réserve d'une période d'essai se terminant le <strong>{{ contract.essaiEndDate|date('d/m/Y') }}</strong>.</p>
            {% else %}
            {% if contract.type == 'CDI' %}
            <p>Le présent contrat est conclu sous réserve d'une période d'essai de <strong>3 mois</strong>, renouvelable une fois par accord écrit des parties.</p>
            {% else %}
            <p>Conformément à l'article L. 1242-10 du Code du travail, la période d'essai ne peut excéder :</p>
            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">1 jour par semaine dans la limite de 2 semaines pour un contrat inférieur ou égal à 6 mois</li>
                <li class="list-item">1 mois pour un contrat supérieur à 6 mois</li>
            </ul>
            {% endif %}
            {% endif %}

            <p style="margin-top: 15px;">Pendant la période d'essai, chacune des parties peut rompre librement le contrat de travail, sous réserve de respecter un délai de prévenance :</p>
            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">24 heures en deçà de 8 jours de présence</li>
                <li class="list-item">48 heures entre 8 jours et 1 mois de présence</li>
                <li class="list-item">2 semaines après 1 mois de présence</li>
            </ul>
        </div>
    </div>

    <!-- Article 6 : Rémunération -->
    <div class="section">
        <h2>ARTICLE 6 - RÉMUNÉRATION</h2>
        <div class="article-content">
            <p>En contrepartie de l'exécution de ses missions, le Salarié percevra :</p>
            
            <div class="info-box">
                <p><span class="info-label">Salaire mensuel brut :</span> <strong>{{ contract.baseSalary|number_format(2, ',', ' ') }} €</strong></p>
                {% if contract.activityRate and contract.activityRate < 1 %}
                <p><span class="info-label">Base temps plein équivalent :</span> {{ (contract.baseSalary / contract.activityRate)|number_format(2, ',', ' ') }} €</p>
                {% endif %}
            </div>

            <p style="margin-top: 15px;">Le salaire sera versé mensuellement, par virement bancaire, le dernier jour ouvrable du mois pour le mois en cours.</p>

            {% if employee.iban %}
            <div class="info-box">
                <p><span class="info-label">IBAN :</span> {{ employee.iban }}</p>
                {% if employee.bic %}
                <p><span class="info-label">BIC :</span> {{ employee.bic }}</p>
                {% endif %}
            </div>
            {% endif %}
        </div>
    </div>

    <!-- Article 7 : Congés payés -->
    <div class="section">
        <h2>ARTICLE 7 - CONGÉS PAYÉS</h2>
        <div class="article-content">
            <p>Le Salarié bénéficie de congés payés conformément aux dispositions légales en vigueur, soit 2,5 jours ouvrables par mois de travail effectif, soit 30 jours ouvrables (5 semaines) par année complète de travail.</p>

            <p style="margin-top: 15px;">Les dates de congés sont fixées d'un commun accord entre le Salarié et l'Employeur, en tenant compte des nécessités du service et dans le respect d'un délai de prévenance d'un mois.</p>

            <p style="margin-top: 15px;">En raison de la spécificité de l'activité (accueil en continu), le Salarié s'engage à respecter le planning prévisionnel des congés établi par la Direction.</p>
        </div>
    </div>

    <!-- Article 8 : Arrêt maladie et accidents -->
    <div class="section">
        <h2>ARTICLE 8 - MALADIE, ACCIDENT ET MATERNITÉ</h2>
        <div class="article-content">
            <p>En cas d'absence pour maladie, accident ou maternité, le Salarié doit en informer immédiatement l'Employeur par téléphone et faire parvenir un certificat médical dans les 48 heures par courrier recommandé ou par mail.</p>

            <p style="margin-top: 15px;">Les conditions d'indemnisation sont fixées conformément aux dispositions légales et conventionnelles applicables.</p>

            <div class="important-notice">
                <p><strong>Procédure obligatoire :</strong></p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li class="list-item">Appel téléphonique immédiat à la Direction : 04.68.70.77.62 ou 06.23.62.15.63</li>
                    <li class="list-item">Envoi du certificat médical sous 48h</li>
                    <li class="list-item">Information sur la durée prévisible de l'absence</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Article 9 : Protection sociale -->
    <div class="section">
        <h2>ARTICLE 9 - PROTECTION SOCIALE COMPLÉMENTAIRE</h2>
        <div class="article-content">
            <p>Le Salarié bénéficie des régimes de protection sociale obligatoires suivants :</p>
            
            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item"><strong>Mutuelle santé collective obligatoire :</strong> selon les dispositions en vigueur dans l'établissement</li>
                <li class="list-item"><strong>Régime de prévoyance :</strong> garantissant une couverture en cas d'incapacité, d'invalidité ou de décès</li>
                <li class="list-item"><strong>Retraite complémentaire :</strong> affiliation obligatoire à l'AGIRC-ARRCO</li>
            </ul>

            <p style="margin-top: 15px;">Les modalités de fonctionnement et les niveaux de garanties sont précisés dans les notices d'information remises au Salarié.</p>
        </div>
    </div>

    <!-- Article 10 : Formation professionnelle -->
    <div class="section">
        <h2>ARTICLE 10 - FORMATION PROFESSIONNELLE</h2>
        <div class="article-content">
            <p>Le Salarié bénéficie des actions de formation prévues dans le cadre du plan de développement des compétences de l'établissement.</p>
            
            <p style="margin-top: 15px;">Le Salarié peut également mobiliser son Compte Personnel de Formation (CPF) pour accéder à des formations qualifiantes ou certifiantes.</p>

            <div class="important-notice">
                <p><strong>Formations spécifiques au secteur :</strong></p>
                <p>Le Salarié pourra être amené à suivre des formations obligatoires liées à l'accueil de jeunes en difficultés :</p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li class="list-item">Prévention et gestion des situations de violence</li>
                    <li class="list-item">Droits de l'enfant et protection de l'enfance</li>
                    <li class="list-item">Premiers secours (PSC1)</li>
                    <li class="list-item">Bientraitance et éthique professionnelle</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Article 11 : Obligations du salarié -->
    <div class="section">
        <h2>ARTICLE 11 - OBLIGATIONS DU SALARIÉ</h2>
        <div class="article-content">
            <p>Le Salarié s'engage à :</p>
            
            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">Exercer ses fonctions avec compétence, conscience professionnelle et assiduité</li>
                <li class="list-item">Respecter les règles de fonctionnement de l'établissement et les consignes qui lui seront données</li>
                <li class="list-item">Respecter le secret professionnel et la confidentialité des informations concernant les jeunes accueillis et leurs familles</li>
                <li class="list-item">Se conformer aux règles d'hygiène et de sécurité en vigueur</li>
                <li class="list-item">Respecter le règlement intérieur de l'établissement</li>
                <li class="list-item">Faire preuve de loyauté envers l'Employeur</li>
                <li class="list-item">Adopter une posture éducative bienveillante et non-violente</li>
                <li class="list-item">Signaler immédiatement toute situation préoccupante ou dangereuse</li>
                <li class="list-item">Travailler en cohérence avec le projet éducatif de l'établissement</li>
            </ul>

            <div class="important-notice">
                <p><strong>⚠ SECRET PROFESSIONNEL RENFORCÉ</strong></p>
                <p>Le Salarié s'engage à ne divulguer aucune information confidentielle relative aux jeunes accueillis, à leurs familles, à leur situation personnelle ou aux méthodes de travail de l'établissement, et ce même après la cessation de son contrat.</p>
                <p style="margin-top: 10px;">Toute violation du secret professionnel peut entraîner des sanctions disciplinaires pouvant aller jusqu'au licenciement pour faute grave, ainsi que des poursuites pénales.</p>
            </div>
        </div>
    </div>

    <!-- Article 12 : Convention collective et règlement intérieur -->
    <div class="section">
        <h2>ARTICLE 12 - CONVENTION COLLECTIVE ET RÈGLEMENT INTÉRIEUR</h2>
        <div class="article-content">
            <p>Le présent contrat est soumis aux dispositions du Code du travail et aux conventions collectives applicables au secteur médico-social et aux lieux de vie et d'accueil.</p>

            <p style="margin-top: 15px;">Le Salarié déclare avoir pris connaissance du règlement intérieur de l'établissement et du projet éducatif dont des exemplaires lui ont été remis. Il s'engage à en respecter toutes les dispositions.</p>
        </div>
    </div>

    <!-- Article 13 : Modification du contrat -->
    <div class="section">
        <h2>ARTICLE 13 - MODIFICATION DU CONTRAT</h2>
        <div class="article-content">
            <p>Toute modification d'un élément essentiel du présent contrat (rémunération, qualification, durée du travail, lieu de travail) ne pourra intervenir que par accord écrit entre les parties, sous forme d'avenant au présent contrat.</p>
        </div>
    </div>

    <!-- Article 14 : Rupture du contrat -->
    <div class="section">
        <h2>ARTICLE 14 - RUPTURE DU CONTRAT</h2>
        <div class="article-content">
            {% if contract.type == 'CDI' %}
            <p>Le présent contrat peut être rompu :</p>
            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">Par démission du Salarié, sous réserve du respect d'un préavis dont la durée est fixée par la convention collective ou, à défaut, par la loi</li>
                <li class="list-item">Par licenciement par l'Employeur, selon les dispositions légales en vigueur</li>
                <li class="list-item">Par rupture conventionnelle, d'un commun accord entre les parties</li>
                <li class="list-item">Pour cas de force majeure</li>
            </ul>
            {% else %}
            <p>Le contrat prendra automatiquement fin à la date prévue, soit le <strong>{{ contract.endDate|date('d/m/Y') }}</strong>, sauf renouvellement ou transformation en CDI.</p>
            
            <p style="margin-top: 15px;">Il pourra également être rompu de manière anticipée :</p>
            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">En cas de faute grave ou lourde</li>
                <li class="list-item">En cas de force majeure</li>
                <li class="list-item">D'un commun accord entre les parties</li>
                <li class="list-item">À l'initiative du Salarié justifiant d'une embauche en CDI</li>
            </ul>
            {% endif %}
        </div>
    </div>

    <!-- Article 15 : Documents remis -->
    <div class="section">
        <h2>ARTICLE 15 - DOCUMENTS REMIS AU SALARIÉ</h2>
        <div class="article-content">
            <p>L'Employeur remet au Salarié les documents suivants :</p>
            
            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">Un exemplaire du présent contrat de travail</li>
                <li class="list-item">Le règlement intérieur de l'établissement</li>
                <li class="list-item">Le projet éducatif de l'Association NEW LIFE</li>
                <li class="list-item">Les documents relatifs à la mutuelle et à la prévoyance</li>
                <li class="list-item">La fiche de poste</li>
                <li class="list-item">Les protocoles et procédures internes</li>
            </ul>

            <p style="margin-top: 15px;">Le Salarié reconnaît avoir reçu l'ensemble de ces documents.</p>
        </div>
    </div>

    <!-- Article 16 : Données personnelles -->
    <div class="section">
        <h2>ARTICLE 16 - PROTECTION DES DONNÉES PERSONNELLES</h2>
        <div class="article-content">
            <p>Conformément au Règlement Général sur la Protection des Données (RGPD) et à la loi Informatique et Libertés, le Salarié est informé que ses données personnelles font l'objet d'un traitement informatique destiné à la gestion administrative du personnel.</p>
            
            <p style="margin-top: 15px;">Le Salarié dispose d'un droit d'accès, de rectification, d'effacement et de portabilité des données le concernant, ainsi que d'un droit d'opposition et de limitation du traitement. Ces droits peuvent être exercés auprès de la Direction de l'Association NEW LIFE.</p>
            
            <p style="margin-top: 15px;"><strong>Contact :</strong> Association NEW LIFE - 15 chemin des Gerbiers - 11120 ARGELIERS - Tél : 04.68.70.77.62</p>
        </div>
    </div>

    <!-- Article 17 : Dispositions finales -->
    <div class="section">
        <h2>ARTICLE 17 - DISPOSITIONS FINALES</h2>
        <div class="article-content">
            <p>Le présent contrat est rédigé en deux exemplaires originaux, dont un est remis au Salarié.</p>
            
            <p style="margin-top: 15px;">Pour tout litige relatif à l'interprétation ou à l'exécution du présent contrat, les parties s'efforceront de trouver une solution amiable. À défaut, le litige sera porté devant le Conseil de Prud'hommes compétent.</p>
        </div>
    </div>

    <!-- Signatures -->
    <div class="section" style="margin-top: 60px;">
        <p style="text-align: center; font-weight: bold; margin-bottom: 30px;">FAIT EN DEUX EXEMPLAIRES ORIGINAUX</p>
        
        <table style="margin-top: 30px;">
            <tr>
                <td width="50%" style="border-right: 1px solid #bdc3c7;">
                    <div class="signature-box">
                        <p class="signature-title">L'EMPLOYEUR</p>
                        <p>Association NEW LIFE</p>
                        <p style="margin-top: 10px;">M. Fernand ADJOVI</p>
                        <p>Directeur</p>
                        <p style="margin-top: 15px;">À Argeliers, le {{ currentDate|date('d/m/Y') }}</p>
                        <p style="margin-top: 25px; font-style: italic;">Signature et cachet :</p>
                        <div class="signature-line"></div>
                    </div>
                </td>
                <td width="50%">
                    <div class="signature-box">
                        <p class="signature-title">LE SALARIÉ</p>
                        <p>{{ employee.fullName }}</p>
                        <p>{{ employee.position }}</p>
                        {% if contract.signedAt %}
                        <p style="margin-top: 15px;">Signé électroniquement le {{ contract.signedAt|date('d/m/Y à H:i') }}</p>
                        <p style="font-style: italic; margin-top: 10px;">✓ Signature électronique certifiée</p>
                        {% else %}
                        <p style="margin-top: 15px;">À _________________, le _______________</p>
                        <p style="margin-top: 25px; font-style: italic;">Signature précédée de la mention « Lu et approuvé, bon pour accord » :</p>
                        <div class="signature-line"></div>
                        {% endif %}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Pied de page / Métadonnées -->
    <div class="footer">
        <p>Document généré automatiquement par RH NewLife le {{ currentDate|date('d/m/Y à H:i') }}</p>
        <p>Association NEW LIFE - 15 chemin des Gerbiers - 11120 ARGELIERS</p>
        <p>SIRET : 838 188 712 00015 - R.N.A : W113003128</p>
        {% if contract.signatureIp %}
        <p style="margin-top: 10px;">Signature électronique effectuée depuis l'adresse IP : {{ contract.signatureIp }}</p>
        {% endif %}
        <p style="margin-top: 10px; font-size: 8pt;">Ce document est conforme aux dispositions du Code du travail français.</p>
    </div>
</body>
</html>
HTML;
    }

    private function getTemplateCDDContent(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de Travail - {{ contract.type }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #000;
            padding: 40px 60px;
            max-width: 210mm;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 18pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .header .employer-info {
            font-size: 12pt;
            margin-top: 15px;
            line-height: 1.4;
        }

        .header .employer-name {
            font-weight: bold;
            font-size: 14pt;
            color: #34495e;
        }

        .section {
            margin: 30px 0;
            page-break-inside: avoid;
        }

        .section h2 {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
            color: #2c3e50;
            border-bottom: 2px solid #bdc3c7;
            padding-bottom: 5px;
        }

        .section p {
            margin: 10px 0;
            text-align: justify;
        }

        .info-box {
            background-color: #ecf0f1;
            padding: 15px;
            border-left: 4px solid #3498db;
            margin: 15px 0;
        }

        .info-box p {
            margin: 5px 0;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            min-width: 180px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        td {
            padding: 15px;
            vertical-align: top;
        }

        .signature-box {
            border: 1px solid #bdc3c7;
            min-height: 120px;
            padding: 15px;
            margin-top: 10px;
        }

        .signature-title {
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            margin-top: 60px;
            width: 200px;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #bdc3c7;
            font-size: 9pt;
            color: #7f8c8d;
            text-align: center;
        }

        .article-content {
            margin-left: 20px;
        }

        .important-notice {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .list-item {
            margin-left: 30px;
            margin-bottom: 8px;
        }

        @media print {
            body {
                padding: 20mm;
            }

            .section {
                page-break-inside: avoid;
            }
        }

        @page {
            margin: 20mm;
        }
    </style>
</head>
<body>
    <!-- En-tête du contrat -->
    <div class="header">
        <h1>CONTRAT DE TRAVAIL À DURÉE DÉTERMINÉE</h1>
        <div class="employer-info">
            <p class="employer-name">Association NEW LIFE</p>
            <p>Lieu de Vie et d'Accueil</p>
            <p>15 chemin des Gerbiers - 11120 ARGELIERS</p>
            <p>SIRET : 838 188 712 00015 - Code APE : 8790A</p>
            <p>R.N.A : W113003128</p>
            <p>Tél : 04.68.70.77.62</p>
        </div>
    </div>

    <!-- Parties contractantes -->
    <div class="section">
        <h2>ENTRE LES SOUSSIGNÉS :</h2>

        <div class="article-content">
            <p><strong>L'EMPLOYEUR :</strong></p>
            <div class="info-box">
                <p><span class="info-label">Dénomination :</span> Association NEW LIFE</p>
                <p><span class="info-label">Forme juridique :</span> Association loi 1901 à but non lucratif</p>
                <p><span class="info-label">SIRET :</span> 838 188 712 00015</p>
                <p><span class="info-label">Code APE :</span> 8790A</p>
                <p><span class="info-label">R.N.A :</span> W113003128</p>
                <p><span class="info-label">Adresse :</span> 15 chemin des Gerbiers, 11120 ARGELIERS</p>
                <p><span class="info-label">Téléphone :</span> 04.68.70.77.62</p>
                <p><span class="info-label">Représentée par :</span> M. Fernand ADJOVI, Directeur</p>
            </div>

            <p style="margin-top: 30px;"><strong>D'UNE PART,</strong></p>
            <p style="margin-top: 20px;"><strong>ET :</strong></p>

            <div class="info-box">
                <p><span class="info-label">Nom et Prénom :</span> <strong>{{ employee.fullName }}</strong></p>
                <p><span class="info-label">Matricule :</span> {{ employee.matricule }}</p>
                <p><span class="info-label">Adresse :</span> {{ employee.address }}</p>
                <p><span class="info-label">Email :</span> {{ employee.email }}</p>
                {% if employee.phone %}
                <p><span class="info-label">Téléphone :</span> {{ employee.phone }}</p>
                {% endif %}
                {% if employee.familyStatus %}
                <p><span class="info-label">Situation familiale :</span> {{ employee.familyStatus }}</p>
                {% endif %}
                {% if employee.children %}
                <p><span class="info-label">Nombre d'enfants :</span> {{ employee.children }}</p>
                {% endif %}
            </div>

            <p style="margin-top: 30px;"><strong>Ci-après dénommé(e) « LE SALARIÉ »</strong></p>
            <p style="margin-top: 20px;"><strong>D'AUTRE PART,</strong></p>
        </div>
    </div>

    <!-- Préambule -->
    <div class="section">
        <h2>PRÉAMBULE</h2>
        <div class="article-content">
            <p>L'Association NEW LIFE a pour objet l'organisation administrative et la gestion d'un lieu de vie - lieu d'accueil recevant des jeunes en difficultés et/ou en danger, dans le but de favoriser leur réinsertion sociale et/ou familiale.</p>

            <p style="margin-top: 15px;">Le présent contrat est conclu en application de l'article L. 1242-2 du Code du travail pour répondre à un besoin temporaire de l'établissement.</p>
        </div>
    </div>

    <!-- IL A ÉTÉ CONVENU CE QUI SUIT -->
    <div class="section" style="text-align: center; margin: 40px 0;">
        <p style="font-size: 13pt; font-weight: bold;">IL A ÉTÉ CONVENU ET ARRÊTÉ CE QUI SUIT :</p>
    </div>

    <!-- Article 1 : Engagement -->
    <div class="section">
        <h2>ARTICLE 1 - ENGAGEMENT ET DURÉE</h2>
        <div class="article-content">
            <p>L'Employeur engage le Salarié qui accepte, aux clauses et conditions suivantes :</p>

            <div class="info-box" style="margin-top: 15px;">
                <p><span class="info-label">Poste occupé :</span> <strong>{{ employee.position }}</strong></p>
                {% if employee.structure %}
                <p><span class="info-label">Structure d'affectation :</span> {{ employee.structure }}</p>
                {% endif %}
                {% if contract.villa %}
                <p><span class="info-label">Villa :</span> {{ contract.villa }}</p>
                {% endif %}
                <p><span class="info-label">Type de contrat :</span> <strong>{{ contract.type }}</strong></p>
                <p><span class="info-label">Date de début :</span> <strong>{{ contract.startDate|date('d/m/Y') }}</strong></p>
                {% if contract.endDate %}
                <p><span class="info-label">Date de fin :</span> <strong>{{ contract.endDate|date('d/m/Y') }}</strong></p>
                <p><span class="info-label">Durée du contrat :</span> Du {{ contract.startDate|date('d/m/Y') }} au {{ contract.endDate|date('d/m/Y') }}</p>
                {% endif %}
            </div>

            <p style="margin-top: 15px;">Le Salarié exercera ses fonctions sous l'autorité hiérarchique et conformément aux directives qui lui seront données par la Direction.</p>

            <p style="margin-top: 15px;"><strong>À l'échéance du terme</strong>, le contrat prendra fin automatiquement, sans formalité particulière, sauf renouvellement ou transformation en CDI par accord écrit des parties.</p>
        </div>
    </div>

    <!-- Article 2 : Motif du recours -->
    <div class="section">
        <h2>ARTICLE 2 - MOTIF DU RECOURS AU CDD</h2>
        <div class="article-content">
            <p>Conformément à l'article L. 1242-2 du Code du travail, le présent contrat est conclu pour l'un des motifs suivants :</p>

            <div class="important-notice">
                <p><strong>Motif de recours :</strong></p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li class="list-item">Accroissement temporaire d'activité de l'établissement</li>
                    <li class="list-item">Remplacement d'un salarié absent ou dont le contrat est suspendu</li>
                    <li class="list-item">Besoin ponctuel lié aux spécificités de l'activité</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Article 3 : Fonctions et Missions -->
    <div class="section">
        <h2>ARTICLE 3 - FONCTIONS ET MISSIONS</h2>
        <div class="article-content">
            <p>Le Salarié aura pour missions principales celles correspondant au poste de <strong>{{ employee.position }}</strong>, notamment :</p>

            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">Assurer l'accueil et l'accompagnement des jeunes au quotidien</li>
                <li class="list-item">Participer à la mise en œuvre du projet éducatif de l'établissement</li>
                <li class="list-item">Contribuer à la réinsertion sociale et/ou familiale des jeunes accueillis</li>
                <li class="list-item">Travailler en équipe pluridisciplinaire</li>
                <li class="list-item">Participer aux réunions d'équipe et aux temps institutionnels</li>
                <li class="list-item">Assurer la sécurité physique et affective des jeunes</li>
                <li class="list-item">Rédiger les écrits professionnels nécessaires au suivi des jeunes</li>
            </ul>
        </div>
    </div>

    <!-- Article 4 : Lieu de travail -->
    <div class="section">
        <h2>ARTICLE 4 - LIEU DE TRAVAIL</h2>
        <div class="article-content">
            <div class="info-box">
                <p><span class="info-label">Lieu principal :</span> 15 chemin des Gerbiers, 11120 ARGELIERS</p>
                {% if contract.villa %}
                <p><span class="info-label">Villa affectée :</span> {{ contract.villa }}</p>
                {% endif %}
            </div>

            <p style="margin-top: 15px;">Le Salarié pourra être amené à effectuer des déplacements ponctuels dans le cadre de ses missions (accompagnements extérieurs, transports des jeunes, etc.).</p>
        </div>
    </div>

    <!-- Article 5 : Durée du travail -->
    <div class="section">
        <h2>ARTICLE 5 - DURÉE DU TRAVAIL</h2>
        <div class="article-content">
            <div class="info-box">
                <p><span class="info-label">Durée hebdomadaire :</span> <strong>{{ contract.weeklyHours }} heures</strong></p>
                {% if contract.activityRate and contract.activityRate < 1 %}
                <p><span class="info-label">Taux d'activité :</span> {{ (contract.activityRate * 100)|number_format(0) }}%</p>
                <p><span class="info-label">Équivalent temps plein :</span> {{ (contract.activityRate * 35)|number_format(2) }} heures/semaine</p>
                {% endif %}
                {% if contract.workingDaysFormatted %}
                <p><span class="info-label">Jours travaillés :</span> {{ contract.workingDaysFormatted }}</p>
                {% endif %}
            </div>

            <p style="margin-top: 15px;">La durée légale du travail est fixée à 35 heures par semaine. Les horaires de travail pourront être répartis selon les nécessités du service.</p>

            <div class="important-notice">
                <p><strong>⚠ SPÉCIFICITÉS DU LIEU DE VIE</strong></p>
                <p>En raison de la nature de l'activité (accueil 24h/24 de jeunes en difficultés), le Salarié est susceptible d'effectuer :</p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li class="list-item">Du travail en soirée et/ou de nuit</li>
                    <li class="list-item">Du travail le week-end et jours fériés selon planning</li>
                    <li class="list-item">Des astreintes selon les besoins du service</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Article 6 : Période d'essai -->
    <div class="section">
        <h2>ARTICLE 6 - PÉRIODE D'ESSAI</h2>
        <div class="article-content">
            {% if contract.essaiEndDate %}
            <p>Le présent contrat est conclu sous réserve d'une période d'essai se terminant le <strong>{{ contract.essaiEndDate|date('d/m/Y') }}</strong>.</p>
            {% else %}
            <p>Conformément à l'article L. 1242-10 du Code du travail, la période d'essai ne peut excéder :</p>
            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">1 jour par semaine dans la limite de 2 semaines pour un contrat inférieur ou égal à 6 mois</li>
                <li class="list-item">1 mois pour un contrat supérieur à 6 mois</li>
            </ul>
            {% endif %}

            <p style="margin-top: 15px;">Pendant la période d'essai, chacune des parties peut rompre librement le contrat, sous réserve de respecter un délai de prévenance :</p>
            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">24 heures en deçà de 8 jours de présence</li>
                <li class="list-item">48 heures entre 8 jours et 1 mois de présence</li>
                <li class="list-item">2 semaines après 1 mois de présence</li>
            </ul>
        </div>
    </div>

    <!-- Article 7 : Rémunération -->
    <div class="section">
        <h2>ARTICLE 7 - RÉMUNÉRATION</h2>
        <div class="article-content">
            <p>En contrepartie de l'exécution de ses missions, le Salarié percevra :</p>

            <div class="info-box">
                <p><span class="info-label">Salaire mensuel brut :</span> <strong>{{ contract.baseSalary|number_format(2, ',', ' ') }} €</strong></p>
                {% if contract.activityRate and contract.activityRate < 1 %}
                <p><span class="info-label">Base temps plein équivalent :</span> {{ (contract.baseSalary / contract.activityRate)|number_format(2, ',', ' ') }} €</p>
                {% endif %}
            </div>

            <p style="margin-top: 15px;">Le salaire sera versé mensuellement, par virement bancaire, le dernier jour ouvrable du mois pour le mois en cours.</p>

            {% if employee.iban %}
            <div class="info-box">
                <p><span class="info-label">IBAN :</span> {{ employee.iban }}</p>
                {% if employee.bic %}
                <p><span class="info-label">BIC :</span> {{ employee.bic }}</p>
                {% endif %}
            </div>
            {% endif %}
        </div>
    </div>

    <!-- Article 8 : Congés payés -->
    <div class="section">
        <h2>ARTICLE 8 - CONGÉS PAYÉS</h2>
        <div class="article-content">
            <p>Le Salarié bénéficie de congés payés conformément aux dispositions légales en vigueur, soit 2,5 jours ouvrables par mois de travail effectif.</p>

            <p style="margin-top: 15px;">Les dates de congés sont fixées d'un commun accord entre le Salarié et l'Employeur, en tenant compte des nécessités du service.</p>

            <div class="important-notice">
                <p><strong>Indemnité de fin de contrat :</strong></p>
                <p>À l'issue du contrat, le Salarié percevra une indemnité de fin de contrat égale à 10% de la rémunération totale brute perçue, sauf cas d'embauche en CDI, rupture anticipée à l'initiative du salarié, ou faute grave.</p>
            </div>
        </div>
    </div>

    <!-- Article 9 : Maladie et accidents -->
    <div class="section">
        <h2>ARTICLE 9 - MALADIE, ACCIDENT ET MATERNITÉ</h2>
        <div class="article-content">
            <p>En cas d'absence pour maladie, accident ou maternité, le Salarié doit en informer immédiatement l'Employeur par téléphone et faire parvenir un certificat médical dans les 48 heures.</p>

            <div class="important-notice">
                <p><strong>Procédure obligatoire :</strong></p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li class="list-item">Appel téléphonique immédiat à la Direction : 04.68.70.77.62 ou 06.23.62.15.63</li>
                    <li class="list-item">Envoi du certificat médical sous 48h</li>
                    <li class="list-item">Information sur la durée prévisible de l'absence</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Article 10 : Obligations du salarié -->
    <div class="section">
        <h2>ARTICLE 10 - OBLIGATIONS DU SALARIÉ</h2>
        <div class="article-content">
            <p>Le Salarié s'engage à :</p>

            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">Exercer ses fonctions avec compétence, conscience professionnelle et assiduité</li>
                <li class="list-item">Respecter les règles de fonctionnement de l'établissement et les consignes données</li>
                <li class="list-item">Respecter le secret professionnel et la confidentialité des informations</li>
                <li class="list-item">Se conformer aux règles d'hygiène et de sécurité en vigueur</li>
                <li class="list-item">Respecter le règlement intérieur de l'établissement</li>
                <li class="list-item">Adopter une posture éducative bienveillante et non-violente</li>
                <li class="list-item">Signaler immédiatement toute situation préoccupante ou dangereuse</li>
            </ul>

            <div class="important-notice">
                <p><strong>⚠ SECRET PROFESSIONNEL RENFORCÉ</strong></p>
                <p>Le Salarié s'engage à ne divulguer aucune information confidentielle relative aux jeunes accueillis, et ce même après la cessation de son contrat.</p>
            </div>
        </div>
    </div>

    <!-- Article 11 : Rupture anticipée -->
    <div class="section">
        <h2>ARTICLE 11 - RUPTURE ANTICIPÉE DU CONTRAT</h2>
        <div class="article-content">
            <p>Le contrat prendra automatiquement fin à la date prévue. Il pourra être rompu de manière anticipée dans les cas suivants :</p>

            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">En cas de faute grave ou lourde</li>
                <li class="list-item">En cas de force majeure</li>
                <li class="list-item">D'un commun accord entre les parties</li>
                <li class="list-item">À l'initiative du Salarié justifiant d'une embauche en CDI</li>
                <li class="list-item">En cas d'inaptitude médicalement constatée</li>
            </ul>
        </div>
    </div>

    <!-- Article 12 : Documents remis -->
    <div class="section">
        <h2>ARTICLE 12 - DOCUMENTS REMIS AU SALARIÉ</h2>
        <div class="article-content">
            <p>L'Employeur remet au Salarié les documents suivants :</p>

            <ul style="margin-left: 30px; margin-top: 10px;">
                <li class="list-item">Un exemplaire du présent contrat de travail</li>
                <li class="list-item">Le règlement intérieur de l'établissement</li>
                <li class="list-item">Le projet éducatif de l'Association NEW LIFE</li>
                <li class="list-item">Les documents relatifs à la mutuelle et à la prévoyance</li>
                <li class="list-item">La fiche de poste</li>
            </ul>
        </div>
    </div>

    <!-- Article 13 : Dispositions finales -->
    <div class="section">
        <h2>ARTICLE 13 - DISPOSITIONS FINALES</h2>
        <div class="article-content">
            <p>Le présent contrat est rédigé en deux exemplaires originaux, dont un est remis au Salarié.</p>

            <p style="margin-top: 15px;">Pour tout litige relatif à l'interprétation ou à l'exécution du présent contrat, les parties s'efforceront de trouver une solution amiable. À défaut, le litige sera porté devant le Conseil de Prud'hommes compétent.</p>
        </div>
    </div>

    <!-- Signatures -->
    <div class="section" style="margin-top: 60px;">
        <p style="text-align: center; font-weight: bold; margin-bottom: 30px;">FAIT EN DEUX EXEMPLAIRES ORIGINAUX</p>

        <table style="margin-top: 30px;">
            <tr>
                <td width="50%" style="border-right: 1px solid #bdc3c7;">
                    <div class="signature-box">
                        <p class="signature-title">L'EMPLOYEUR</p>
                        <p>Association NEW LIFE</p>
                        <p style="margin-top: 10px;">M. Fernand ADJOVI</p>
                        <p>Directeur</p>
                        <p style="margin-top: 15px;">À Argeliers, le {{ currentDate|date('d/m/Y') }}</p>
                        <p style="margin-top: 25px; font-style: italic;">Signature et cachet :</p>
                        <div class="signature-line"></div>
                    </div>
                </td>
                <td width="50%">
                    <div class="signature-box">
                        <p class="signature-title">LE SALARIÉ</p>
                        <p>{{ employee.fullName }}</p>
                        <p>{{ employee.position }}</p>
                        {% if contract.signedAt %}
                        <p style="margin-top: 15px;">Signé électroniquement le {{ contract.signedAt|date('d/m/Y à H:i') }}</p>
                        <p style="font-style: italic; margin-top: 10px;">✓ Signature électronique certifiée</p>
                        {% else %}
                        <p style="margin-top: 15px;">À _________________, le _______________</p>
                        <p style="margin-top: 25px; font-style: italic;">Signature précédée de la mention « Lu et approuvé, bon pour accord » :</p>
                        <div class="signature-line"></div>
                        {% endif %}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Pied de page / Métadonnées -->
    <div class="footer">
        <p>Document généré automatiquement par RH NewLife le {{ currentDate|date('d/m/Y à H:i') }}</p>
        <p>Association NEW LIFE - 15 chemin des Gerbiers - 11120 ARGELIERS</p>
        <p>SIRET : 838 188 712 00015 - R.N.A : W113003128</p>
        {% if contract.signatureIp %}
        <p style="margin-top: 10px;">Signature électronique effectuée depuis l'adresse IP : {{ contract.signatureIp }}</p>
        {% endif %}
        <p style="margin-top: 10px; font-size: 8pt;">Ce document est conforme aux dispositions du Code du travail français.</p>
    </div>
</body>
</html>
HTML;
    }
}
