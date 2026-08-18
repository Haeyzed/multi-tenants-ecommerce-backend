<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Catalog\ProductSearchDriver;
use App\Contracts\Marketplace\SellerPayoutDriverInterface;
use App\Contracts\Notification\PushNotificationProvider;
use App\Contracts\Notification\SmsProvider;
use App\Contracts\Payment\PaymentGateway;
use App\Contracts\Shipping\CarrierHttpClientInterface;
use App\Models\Tenant\Account;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Brand;
use App\Models\Tenant\Category;
use App\Models\Tenant\Cms\BlogCategory;
use App\Models\Tenant\Cms\BlogPost;
use App\Models\Tenant\Cms\Page;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerGroup;
use App\Models\Tenant\CustomerSegment;
use App\Models\Tenant\Delivery;
use App\Models\Tenant\Department;
use App\Models\Tenant\Designation;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeSalary;
use App\Models\Tenant\FlashSale;
use App\Models\Tenant\GiftCard;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\LeaveType;
use App\Models\Tenant\LoyaltyAccount;
use App\Models\Tenant\LoyaltyProgram;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderReturn;
use App\Models\Tenant\PayrollItem;
use App\Models\Tenant\PayrollRun;
use App\Models\Tenant\PosSession;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductAttribute;
use App\Models\Tenant\ProductBadge;
use App\Models\Tenant\ProductCollection;
use App\Models\Tenant\ProductOption;
use App\Models\Tenant\ProductReview;
use App\Models\Tenant\ProductTag;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Promotion;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Refund;
use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerCommission;
use App\Models\Tenant\SellerGroup;
use App\Models\Tenant\SellerOffer;
use App\Models\Tenant\SellerOrder;
use App\Models\Tenant\SellerPayout;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\ShippingMethod;
use App\Models\Tenant\StoreCreditAccount;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\Tax;
use App\Models\Tenant\TaxZone;
use App\Models\Tenant\Unit;
use App\Models\Tenant\Warehouse;
use App\Policies\Tenant\AccountPolicy;
use App\Policies\Tenant\AttendancePolicy;
use App\Policies\Tenant\BlogCategoryPolicy;
use App\Policies\Tenant\BlogPostPolicy;
use App\Policies\Tenant\BrandPolicy;
use App\Policies\Tenant\CategoryPolicy;
use App\Policies\Tenant\CouponPolicy;
use App\Policies\Tenant\CustomerGroupPolicy;
use App\Policies\Tenant\CustomerPolicy;
use App\Policies\Tenant\CustomerSegmentPolicy;
use App\Policies\Tenant\DeliveryPolicy;
use App\Policies\Tenant\DepartmentPolicy;
use App\Policies\Tenant\DesignationPolicy;
use App\Policies\Tenant\DriverPolicy;
use App\Policies\Tenant\EmployeePolicy;
use App\Policies\Tenant\EmployeeSalaryPolicy;
use App\Policies\Tenant\FlashSalePolicy;
use App\Policies\Tenant\GiftCardPolicy;
use App\Policies\Tenant\InventoryPolicy;
use App\Policies\Tenant\InvoicePolicy;
use App\Policies\Tenant\JournalEntryPolicy;
use App\Policies\Tenant\LeaveRequestPolicy;
use App\Policies\Tenant\LeaveTypePolicy;
use App\Policies\Tenant\LoyaltyAccountPolicy;
use App\Policies\Tenant\LoyaltyProgramPolicy;
use App\Policies\Tenant\OrderPolicy;
use App\Policies\Tenant\OrderReturnPolicy;
use App\Policies\Tenant\PagePolicy;
use App\Policies\Tenant\PayrollItemPolicy;
use App\Policies\Tenant\PayrollRunPolicy;
use App\Policies\Tenant\PosSessionPolicy;
use App\Policies\Tenant\PosTerminalPolicy;
use App\Policies\Tenant\ProductAttributePolicy;
use App\Policies\Tenant\ProductBadgePolicy;
use App\Policies\Tenant\ProductCollectionPolicy;
use App\Policies\Tenant\ProductOptionPolicy;
use App\Policies\Tenant\ProductPolicy;
use App\Policies\Tenant\ProductReviewPolicy;
use App\Policies\Tenant\ProductTagPolicy;
use App\Policies\Tenant\ProductVariantPolicy;
use App\Policies\Tenant\PromotionPolicy;
use App\Policies\Tenant\PurchaseOrderPolicy;
use App\Policies\Tenant\RefundPolicy;
use App\Policies\Tenant\SellerCommissionPolicy;
use App\Policies\Tenant\SellerGroupPolicy;
use App\Policies\Tenant\SellerOfferPolicy;
use App\Policies\Tenant\SellerOrderPolicy;
use App\Policies\Tenant\SellerPayoutPolicy;
use App\Policies\Tenant\SellerPolicy;
use App\Policies\Tenant\ShipmentPolicy;
use App\Policies\Tenant\ShippingMethodPolicy;
use App\Policies\Tenant\StoreCreditAccountPolicy;
use App\Policies\Tenant\SupplierPolicy;
use App\Policies\Tenant\TaxPolicy;
use App\Policies\Tenant\TaxZonePolicy;
use App\Policies\Tenant\UnitPolicy;
use App\Policies\Tenant\WarehousePolicy;
use App\Services\Notification\ChannelResolver;
use App\Services\Notification\Channels\DatabaseChannel;
use App\Services\Notification\Channels\EmailChannel;
use App\Services\Notification\Channels\PushChannel;
use App\Services\Notification\Channels\SmsChannel;
use App\Services\Notification\DeviceTokenService;
use App\Services\Notification\NotificationPreferenceService;
use App\Services\Notification\Push\FcmPushProvider;
use App\Services\Notification\Sms\SmsManager;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\PaymentWebhookManager;
use App\Services\Shipping\CarrierWebhookManager;
use App\Services\Shipping\Http\LaravelCarrierHttpClient;
use App\Services\Shipping\ShippingCarrierManager;
use App\Services\Tenant\Catalog\DatabaseProductSearchDriver;
use App\Services\Tenant\Catalog\ProductRecommendationService;
use App\Services\Tenant\Catalog\Recommendations\PopularProductsProvider;
use App\Services\Tenant\Catalog\Recommendations\RecentlyViewedProvider;
use App\Services\Tenant\Catalog\Recommendations\RelatedProductsProvider;
use App\Services\Tenant\Delivery\DriverAssignmentManager;
use App\Services\Tenant\Marketplace\Payout\ManualPayoutDriver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class);
        $this->app->singleton(PaymentWebhookManager::class);
        $this->app->singleton(ShippingCarrierManager::class);
        $this->app->singleton(CarrierWebhookManager::class);
        $this->app->singleton(DriverAssignmentManager::class);
        $this->app->bind(CarrierHttpClientInterface::class, LaravelCarrierHttpClient::class);

        $this->app->bind(SellerPayoutDriverInterface::class, ManualPayoutDriver::class);

        $this->app->bind(ProductSearchDriver::class, DatabaseProductSearchDriver::class);

        $this->app->singleton(ProductRecommendationService::class, function ($app): ProductRecommendationService {
            return new ProductRecommendationService([
                $app->make(RelatedProductsProvider::class),
                $app->make(PopularProductsProvider::class),
                $app->make(RecentlyViewedProvider::class),
            ]);
        });

        $this->app->bind(PaymentGateway::class, function ($app): PaymentGateway {
            return $app->make(PaymentManager::class)->driver();
        });

        $this->app->singleton(PushNotificationProvider::class, FcmPushProvider::class);
        $this->app->singleton(SmsManager::class);
        $this->app->bind(SmsProvider::class, function ($app): SmsProvider {
            return $app->make(SmsManager::class)->driver();
        });

        $this->app->singleton(ChannelResolver::class, function ($app): ChannelResolver {
            return new ChannelResolver(
                channels: [
                    $app->make(DatabaseChannel::class),
                    $app->make(EmailChannel::class),
                    $app->make(PushChannel::class),
                    $app->make(SmsChannel::class),
                ],
                preferences: $app->make(NotificationPreferenceService::class),
                devices: $app->make(DeviceTokenService::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Coupon::class, CouponPolicy::class);
        Gate::policy(Promotion::class, PromotionPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerGroup::class, CustomerGroupPolicy::class);
        Gate::policy(CustomerSegment::class, CustomerSegmentPolicy::class);
        Gate::policy(Driver::class, DriverPolicy::class);
        Gate::policy(Delivery::class, DeliveryPolicy::class);
        Gate::policy(PosTerminal::class, PosTerminalPolicy::class);
        Gate::policy(PosSession::class, PosSessionPolicy::class);
        Gate::policy(FlashSale::class, FlashSalePolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductVariant::class, ProductVariantPolicy::class);
        Gate::policy(Inventory::class, InventoryPolicy::class);
        Gate::policy(ProductCollection::class, ProductCollectionPolicy::class);
        Gate::policy(ProductTag::class, ProductTagPolicy::class);
        Gate::policy(ProductBadge::class, ProductBadgePolicy::class);
        Gate::policy(ProductReview::class, ProductReviewPolicy::class);
        Gate::policy(ProductOption::class, ProductOptionPolicy::class);
        Gate::policy(ProductAttribute::class, ProductAttributePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(OrderReturn::class, OrderReturnPolicy::class);
        Gate::policy(LoyaltyProgram::class, LoyaltyProgramPolicy::class);
        Gate::policy(LoyaltyAccount::class, LoyaltyAccountPolicy::class);
        Gate::policy(GiftCard::class, GiftCardPolicy::class);
        Gate::policy(StoreCreditAccount::class, StoreCreditAccountPolicy::class);
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(JournalEntry::class, JournalEntryPolicy::class);
        Gate::policy(ShippingMethod::class, ShippingMethodPolicy::class);
        Gate::policy(Shipment::class, ShipmentPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(Seller::class, SellerPolicy::class);
        Gate::policy(SellerGroup::class, SellerGroupPolicy::class);
        Gate::policy(SellerOffer::class, SellerOfferPolicy::class);
        Gate::policy(Tax::class, TaxPolicy::class);
        Gate::policy(TaxZone::class, TaxZonePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);
        Gate::policy(SellerOrder::class, SellerOrderPolicy::class);
        Gate::policy(SellerCommission::class, SellerCommissionPolicy::class);
        Gate::policy(SellerPayout::class, SellerPayoutPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Designation::class, DesignationPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(EmployeeSalary::class, EmployeeSalaryPolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(LeaveType::class, LeaveTypePolicy::class);
        Gate::policy(PayrollRun::class, PayrollRunPolicy::class);
        Gate::policy(PayrollItem::class, PayrollItemPolicy::class);
        Gate::policy(BlogCategory::class, BlogCategoryPolicy::class);
        Gate::policy(BlogPost::class, BlogPostPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);

        Gate::define('viewHrSettings', function ($user): bool {
            return method_exists($user, 'can') && (
                $user->can('hr.settings.view')
                || $user->can('hr.settings.update')
                || $user->can('hr.view')
            );
        });

        Gate::define('updateHrSettings', function ($user): bool {
            return method_exists($user, 'can') && $user->can('hr.settings.update');
        });

        Gate::define('viewHrSummary', function ($user): bool {
            return method_exists($user, 'can') && (
                $user->can('hr.view')
                || $user->can('hr.employees.view')
                || $user->can('hr.payroll.view')
            );
        });

        Gate::before(function ($user, string $ability): ?bool {
            if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });
    }
}
