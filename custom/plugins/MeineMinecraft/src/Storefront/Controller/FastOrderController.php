<?php declare(strict_types=1);

namespace MeineMinecraft\Storefront\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class FastOrderController extends StorefrontController
{
    private AbstractRegisterRoute $registerRoute;
    private AbstractCartOrderRoute $cartOrderRoute;
    private CartService $cartService;
    private EntityRepository $orderRepository;
    private EntityRepository $salutationRepository;
    private AbstractSalesChannelContextFactory $contextFactory;
    private EntityRepository $customerRepository;
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(
        AbstractRegisterRoute $registerRoute,
        AbstractCartOrderRoute $cartOrderRoute,
        CartService $cartService,
        EntityRepository $orderRepository,
        EntityRepository $salutationRepository,
        AbstractSalesChannelContextFactory $contextFactory,
        EntityRepository $customerRepository,
        MailerInterface $mailer,
        LoggerInterface $logger
    ) {
        $this->registerRoute = $registerRoute;
        $this->cartOrderRoute = $cartOrderRoute;
        $this->cartService = $cartService;
        $this->orderRepository = $orderRepository;
        $this->salutationRepository = $salutationRepository;
        $this->contextFactory = $contextFactory;
        $this->customerRepository = $customerRepository;
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    #[Route(path: '/checkout/fast-order', name: 'frontend.checkout.fast-order', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function createFastOrder(Request $request, SalesChannelContext $context): JsonResponse
    {
        $email = $request->request->get('email');
        if (!$email) {
            return new JsonResponse(['success' => false, 'error' => 'Email is required'], 400);
        }

        try {
            $workingContext = $context;

            if (!$context->getCustomer()) {
                $customerData = new RequestDataBag([
                    'guest' => true,
                    'email' => $email,
                    'salutationId' => $this->getDefaultSalutationId($context),
                    'firstName' => 'Quick',
                    'lastName' => 'Order',
                    'storefrontUrl' => $request->getSchemeAndHttpHost(),
                    'billingAddress' => [
                        'firstName' => 'Quick',
                        'lastName' => 'Order',
                        'street' => 'Quick Order Street',
                        'zipcode' => '00000',
                        'city' => 'Quick Order City',
                        'countryId' => $context->getSalesChannel()->getCountryId(),
                    ]
                ]);

                $customerResponse = $this->registerRoute->register($customerData, $context, false);
                $customer = $customerResponse->getCustomer();

                $workingContext = $this->contextFactory->create(
                    $context->getToken(),
                    $context->getSalesChannel()->getId(),
                    [SalesChannelContextService::CUSTOMER_ID => $customer->getId()]
                );
            } else if ($context->getCustomer()->getEmail() !== $email) {
                $this->customerRepository->update([
                    [
                        'id' => $context->getCustomer()->getId(),
                        'email' => $email
                    ]
                ], $context->getContext());
            }

            $cart = $this->cartService->getCart($workingContext->getToken(), $workingContext);
            if ($cart->getLineItems()->count() === 0) {
                return new JsonResponse(['success' => false, 'error' => 'Cart is empty'], 400);
            }

            $orderResponse = $this->cartOrderRoute->order($cart, $workingContext, new RequestDataBag());
            $order = $orderResponse->getOrder();

            $fullOrder = $this->getFullOrder($order->getId(), $workingContext);

            $hash = strtoupper(substr($workingContext->getToken(), 0, 8));
            $this->orderRepository->update([
                [
                    'id' => $order->getId(),
                    'customFields' => ['quick_order_hash' => $hash]
                ]
            ], $workingContext->getContext());

            // Send Confirmation Email using Symfony Mailer directly
            try {
                $this->sendDirectEmail($fullOrder, $hash);
            } catch (\Exception $mailEx) {
                $this->logger->error('FastOrder Mail Direct Error: ' . $mailEx->getMessage());
            }

            return new JsonResponse([
                'success' => true,
                'orderNumber' => $order->getOrderNumber(),
                'hash' => $hash
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function getFullOrder(string $orderId, SalesChannelContext $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('currency');

        return $this->orderRepository->search($criteria, $context->getContext())->first();
    }

    private function sendDirectEmail(OrderEntity $order, string $hash): void
    {
        $customer = $order->getOrderCustomer();
        if (!$customer) return;

        $itemsHtml = '<ul>';
        foreach ($order->getLineItems() as $item) {
            $itemsHtml .= sprintf(
                '<li>%s x %d - %0.2f %s</li>',
                $item->getLabel(),
                $item->getQuantity(),
                $item->getTotalPrice(),
                $order->getCurrency() ? $order->getCurrency()->getSymbol() : '€'
            );
        }
        $itemsHtml .= '</ul>';

        $contentHtml = sprintf(
            '<h2>Поздравляем с заказом #%s!</h2>
            <p>Ваша заявка успешно принята. Для оплаты заказа, пожалуйста, напишите нашему менеджеру в Telegram:</p>
            <p><a href="https://t.me/staytimeofficial">@staytimeofficial</a></p>
            <p><strong>ОБЯЗАТЕЛЬНО укажите номер вашего заказа: %s</strong></p>
            <hr>
            <h3>Детали заказа:</h3>
            %s
            <p><strong>Итого к оплате: %0.2f %s</strong></p>
            <p>Спасибо, что выбрали StayTime!</p>',
            $order->getOrderNumber(),
            $order->getOrderNumber(),
            $itemsHtml,
            $order->getAmountTotal(),
            $order->getCurrency() ? $order->getCurrency()->getSymbol() : '€'
        );

        $email = (new Email())
            ->from('svitlamarina@gmail.com')
            ->to($customer->getEmail())
            ->subject('Подтверждение заказа #' . $order->getOrderNumber())
            ->html($contentHtml);

        $this->mailer->send($email);
    }

    private function getDefaultSalutationId(SalesChannelContext $context): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $salutation = $this->salutationRepository->search($criteria, $context->getContext())->first();
        
        return $salutation ? $salutation->getId() : '';
    }
}
