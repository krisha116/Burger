<?php


namespace App\Form;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Customer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Name')
            ->add('createAt', DateTimeType::class, [
                'widget' => 'single_text',
            ])
            ->add('Total')
            ->add('Status')
            ->add('Customer', EntityType::class, [
                'class' => Customer::class,
                'placeholder' => 'Select a customer',
                'choice_label' => 'name',
                'required' => false,
            ])
            ->add('products', EntityType::class, [
                'class' => Product::class,
                'multiple' => false,
                'expanded' => false,
                'required' => false,
                'placeholder' => 'Select a product',
                'choice_label' => 'name',
            ])
        ;

        // Transform between Collection<Product> in the model and single Product in the form
        $builder->get('products')->addModelTransformer(
            new CallbackTransformer(
                function ($products): ?Product {
                    if ($products instanceof Collection) {
                        return $products->first() ?: null;
                    }
                    return null;
                },
                function ($product) {
                    $collection = new ArrayCollection();
                    if ($product) {
                        $collection->add($product);
                    }
                    return $collection;
                }
            )
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}