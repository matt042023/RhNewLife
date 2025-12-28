<?php

namespace App\Form;

use App\Entity\Astreinte;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AstreinteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startAt', DateTimeType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input rounded-lg border-gray-300'],
            ])
            ->add('endAt', DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input rounded-lg border-gray-300'],
            ])
            ->add('periodLabel', TextType::class, [
                'label' => 'Libellé de la période',
                'required' => false,
                'attr' => [
                    'class' => 'form-input rounded-lg border-gray-300',
                    'placeholder' => 'Ex: S48, Semaine 48',
                ],
            ])
            ->add('educateur', EntityType::class, [
                'label' => 'Éducateur d\'astreinte',
                'class' => User::class,
                'choice_label' => function (User $user) {
                    $villa = $user->getVilla() ? ' (' . $user->getVilla()->getNom() . ')' : '';
                    return $user->getFullName() . $villa;
                },
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.status = :status')
                        ->setParameter('status', User::STATUS_ACTIVE)
                        ->orderBy('u.lastName', 'ASC');
                },
                'required' => false,
                'placeholder' => '-- Sélectionner un éducateur --',
                'attr' => ['class' => 'form-select rounded-lg border-gray-300'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea rounded-lg border-gray-300',
                    'rows' => 3,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Astreinte::class,
        ]);
    }
}
