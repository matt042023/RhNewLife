<?php

namespace App\Form;

use App\Entity\AnnonceInterne;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\File;

class AnnonceInterneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de l\'annonce',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le titre est obligatoire']),
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Le titre ne peut pas depasser {{ limit }} caracteres',
                    ]),
                ],
                'attr' => [
                    'class' => 'glass-input w-full',
                    'placeholder' => 'Titre de l\'annonce',
                ],
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Contenu',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le contenu est obligatoire']),
                ],
                'attr' => [
                    'class' => 'glass-input w-full',
                    'rows' => 10,
                    'placeholder' => 'Contenu de l\'annonce...',
                ],
            ])
            ->add('visibilite', ChoiceType::class, [
                'label' => 'Visibilite',
                'choices' => array_flip(AnnonceInterne::VISIBILITIES),
                'expanded' => false,
                'attr' => [
                    'class' => 'glass-input w-full',
                ],
                'help' => 'Qui peut voir cette annonce',
            ])
            ->add('epingle', CheckboxType::class, [
                'label' => 'Epingler cette annonce',
                'required' => false,
                'help' => 'L\'annonce epinglee apparaitra en premier sur le tableau de bord',
                'attr' => [
                    'class' => 'rounded border-gray-300 text-emerald-600 shadow-sm focus:border-emerald-300 focus:ring focus:ring-emerald-200 focus:ring-opacity-50',
                ],
            ])
            ->add('expirationDays', IntegerType::class, [
                'label' => 'Expiration (jours)',
                'required' => false,
                'data' => 30,
                'constraints' => [
                    new Assert\Range([
                        'min' => 1,
                        'max' => 365,
                        'notInRangeMessage' => 'La duree doit etre entre {{ min }} et {{ max }} jours',
                    ]),
                ],
                'attr' => [
                    'class' => 'glass-input w-full',
                    'min' => 1,
                    'max' => 365,
                ],
                'help' => 'Nombre de jours avant expiration (laisser vide pour pas d\'expiration)',
            ])
            ->add('image', FileType::class, [
                'label' => 'Image (optionnel)',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez telecharger une image valide (JPEG, PNG, GIF, WebP)',
                    ]),
                ],
                'attr' => [
                    'class' => 'glass-input w-full',
                    'accept' => 'image/*',
                ],
                'help' => 'Image d\'illustration (max 2 Mo)',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
