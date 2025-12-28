<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\VisiteMedicale;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VisiteMedicaleFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'fullName',
                'label' => 'Salarié',
                'placeholder' => 'Tous les salariés',
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.status = :status')
                        ->setParameter('status', User::STATUS_ACTIVE)
                        ->orderBy('u.lastName', 'ASC');
                },
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de visite',
                'required' => false,
                'placeholder' => 'Tous les types',
                'choices' => [
                    'Visite d\'embauche' => VisiteMedicale::TYPE_EMBAUCHE,
                    'Visite périodique' => VisiteMedicale::TYPE_PERIODIQUE,
                    'Visite de reprise' => VisiteMedicale::TYPE_REPRISE,
                    'Visite à la demande' => VisiteMedicale::TYPE_DEMANDE,
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'placeholder' => 'Tous les statuts',
                'choices' => [
                    'Programmée' => VisiteMedicale::STATUS_PROGRAMMEE,
                    'Effectuée' => VisiteMedicale::STATUS_EFFECTUEE,
                    'Annulée' => VisiteMedicale::STATUS_ANNULEE,
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('aptitude', ChoiceType::class, [
                'label' => 'Aptitude',
                'required' => false,
                'placeholder' => 'Toutes les aptitudes',
                'choices' => [
                    'Apte' => VisiteMedicale::APTITUDE_APTE,
                    'Apte avec réserve' => VisiteMedicale::APTITUDE_APTE_AVEC_RESERVE,
                    'Inapte' => VisiteMedicale::APTITUDE_INAPTE,
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('dateFrom', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de visite (de)',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateTo', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de visite (à)',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('expiringSoon', ChoiceType::class, [
                'label' => 'À renouveler',
                'choices' => [
                    'Tous' => '',
                    'À renouveler (30 jours)' => '1',
                    'Expirées' => '2',
                ],
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
