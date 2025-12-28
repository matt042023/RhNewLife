<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\VisiteMedicale;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class VisiteMedicaleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'label' => 'Éducateur',
                'required' => true,
                'disabled' => $isEdit,
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('u')
                        ->where('u.status = :status')
                        ->setParameter('status', 'active')
                        ->orderBy('u.lastName', 'ASC');
                },
                'choice_label' => function (User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'attr' => [
                    'class' => 'form-select',
                    'data-controller' => 'select2',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'éducateur est obligatoire'),
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de visite',
                'required' => true,
                'choices' => [
                    'Visite d\'embauche' => VisiteMedicale::TYPE_EMBAUCHE,
                    'Visite périodique' => VisiteMedicale::TYPE_PERIODIQUE,
                    'Visite de reprise' => VisiteMedicale::TYPE_REPRISE,
                    'Visite à la demande' => VisiteMedicale::TYPE_DEMANDE,
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le type de visite est obligatoire'),
                ]
            ])
            ->add('visitDate', DateType::class, [
                'label' => 'Date de la visite',
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de visite est obligatoire'),
                    new Assert\LessThanOrEqual('today', message: 'La date de visite ne peut pas être dans le futur'),
                ]
            ])
            ->add('expiryDate', DateType::class, [
                'label' => 'Date d\'expiration',
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'Date à laquelle la visite médicale devra être renouvelée',
                'constraints' => [
                    new Assert\NotBlank(message: 'La date d\'expiration est obligatoire'),
                    new Assert\GreaterThan('today', message: 'La date d\'expiration doit être dans le futur'),
                ]
            ])
            ->add('medicalOrganization', TextType::class, [
                'label' => 'Organisme médical',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Médecine du Travail ACMS',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'organisme médical est obligatoire'),
                    new Assert\Length(
                        max: 255,
                        maxMessage: 'L\'organisme médical ne peut pas dépasser {{ limit }} caractères'
                    )
                ]
            ])
            ->add('aptitude', ChoiceType::class, [
                'label' => 'Aptitude',
                'required' => true,
                'choices' => [
                    'Apte' => VisiteMedicale::APTITUDE_APTE,
                    'Apte avec réserve' => VisiteMedicale::APTITUDE_APTE_AVEC_RESERVE,
                    'Inapte' => VisiteMedicale::APTITUDE_INAPTE,
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'form-radio-group',
                ],
                'help' => 'Résultat de l\'aptitude médicale déterminé par le médecin du travail',
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'aptitude est obligatoire'),
                ]
            ])
            ->add('observations', TextareaType::class, [
                'label' => 'Observations / Restrictions',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Observations du médecin, restrictions éventuelles, recommandations...',
                ],
                'help' => 'Obligatoire si l\'aptitude est "Apte avec réserve" ou "Inapte"'
            ])
            ->add('certificateFile', FileType::class, [
                'label' => 'Certificat médical (PDF, JPG, PNG)',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf,.jpg,.jpeg,.png',
                ],
                'help' => 'Taille maximale: 10 Mo',
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un fichier PDF, JPG ou PNG valide',
                        'maxSizeMessage' => 'Le fichier est trop volumineux ({{ size }} {{ suffix }}). La taille maximale autorisée est {{ limit }} {{ suffix }}',
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => VisiteMedicale::class,
            'is_edit' => false,
        ]);
    }
}
