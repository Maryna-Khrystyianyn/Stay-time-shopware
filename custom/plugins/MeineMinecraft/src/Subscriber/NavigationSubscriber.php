<?php declare(strict_types=1);

namespace MeineMinecraft\Subscriber;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\AbstractNavigationRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Address\CheckoutAddressPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class NavigationSubscriber implements EventSubscriberInterface
{
    private EntityRepository $categoryRepository;
    private AbstractNavigationRoute $navigationRoute;

    public function __construct(
        EntityRepository $categoryRepository,
        AbstractNavigationRoute $navigationRoute
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->navigationRoute = $navigationRoute;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NavigationPageLoadedEvent::class => 'onPageLoaded',
            CheckoutAddressPageLoadedEvent::class => 'onPageLoaded',
            CheckoutRegisterPageLoadedEvent::class => 'onPageLoaded',
        ];
    }

    public function onPageLoaded(PageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $navigationId = $context->getSalesChannel()->getNavigationCategoryId();

        // Load the navigation tree using the route
        // We set depth 2 to get children of the main categories (like Products)
        $criteria = new Criteria();
        $criteria->setLimit(99);
        
        $request = new Request();
        $request->query->set('depth', '2');
        $request->query->set('buildTree', 'true');

        $navigationResponse = $this->navigationRoute->load($navigationId, $navigationId, $request, $context, $criteria);
        $categories = $navigationResponse->getCategories();

        $productsCategory = null;
        $searchNames = ['products', 'produkte', 'продукти', 'продукты'];

        foreach ($categories as $category) {
            $catName = $category->getTranslation('name') ?: '';
            if (in_array(mb_strtolower($catName, 'UTF-8'), $searchNames, true)) {
                $productsCategory = $category;
                break;
            }
        }

        if ($productsCategory) {
            $subCategories = $productsCategory->getChildren() ?: new CategoryCollection();
            $event->getPage()->addExtension('productsSubcategories', $subCategories);
        }

        // Ensure the header navigation tree is loaded for checkout pages
        $page = $event->getPage();
        if (method_exists($page, 'getHeader') && $page->getHeader() && !$page->getHeader()->getNavigation()) {
            $navigation = new \Shopware\Storefront\Page\Header\HeaderNavigationEntity();
            $navigation->setTree($categories);
            $page->getHeader()->setNavigation($navigation);
        }
    }
}
