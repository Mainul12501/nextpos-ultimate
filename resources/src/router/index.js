import { watch } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import { i18n, t } from '../i18n';
import { useAuthStore } from '../stores/auth';
import { moduleKeyForPath } from '../config/modules';
import { start as progressStart, done as progressDone } from '../lib/progress';
// The shell is needed on every page, so it stays in the entry bundle.
import AdminLayout from '../layouts/AdminLayout.vue';

/**
 * Every page is code-split: with ~386 pages to migrate, one bundle is not an
 * option. Verified working — an earlier `nextSibling` crash was blamed on lazy
 * routes, but the chunks load fine; the trial ran clean once the dependency
 * tree was reinstalled and a bad VNode menu label was removed.
 *
 * The wrapper makes a failed chunk fetch loud instead of surfacing later as an
 * unexplained render error.
 */
const lazy = (name, loader) => () =>
    loader().catch(err => {
        console.error(`[stocky-next] chunk load FAILED for "${name}" — cause of any render error that follows:`, err);
        throw err;
    });

const Ping = lazy('Ping', () => import('../pages/Ping.vue'));
const PosPage = lazy('PosPage', () => import('../pages/pos/PosPage.vue'));
const PosThemePreviewPage = lazy('PosThemePreviewPage', () => import('../pages/settings/PosThemePreviewPage.vue'));
const Dashboard = lazy('Dashboard', () => import('../pages/Dashboard.vue'));
const Brands = lazy('Brands', () => import('../pages/Brands.vue'));
const VehicleCatalog = lazy('VehicleCatalog', () => import('../pages/vehicles/VehicleCatalog.vue'));
const Categories = lazy('Categories', () => import('../pages/Categories.vue'));
const SubCategories = lazy('SubCategories', () => import('../pages/SubCategories.vue'));
const Units = lazy('Units', () => import('../pages/Units.vue'));
const SizeGuides = lazy('SizeGuides', () => import('../pages/SizeGuides.vue'));
const Currencies = lazy('Currencies', () => import('../pages/Currencies.vue'));
const Warehouses = lazy('Warehouses', () => import('../pages/Warehouses.vue'));
const NotFound = lazy('NotFound', () => import('../pages/NotFound.vue'));

// --- Reports (Wave B) ---
const DeadStockReport = lazy('DeadStockReport', () => import('../pages/reports/DeadStockReport.vue'));
const ZeroSalesReport = lazy('ZeroSalesReport', () => import('../pages/reports/ZeroSalesReport.vue'));
const NegativeStockReport = lazy('NegativeStockReport', () => import('../pages/reports/NegativeStockReport.vue'));
const ExpiryReport = lazy('ExpiryReport', () => import('../pages/reports/ExpiryReport.vue'));
const DiscountSummaryReport = lazy('DiscountSummaryReport', () => import('../pages/reports/DiscountSummaryReport.vue'));
const TaxSummaryReport = lazy('TaxSummaryReport', () => import('../pages/reports/TaxSummaryReport.vue'));
const LoyaltyPointsReport = lazy('LoyaltyPointsReport', () => import('../pages/reports/LoyaltyPointsReport.vue'));
const InactiveCustomersReport = lazy('InactiveCustomersReport', () => import('../pages/reports/InactiveCustomersReport.vue'));
const DraftInvoicesReport = lazy('DraftInvoicesReport', () => import('../pages/reports/DraftInvoicesReport.vue'));
const TopSuppliersReport = lazy('TopSuppliersReport', () => import('../pages/reports/TopSuppliersReport.vue'));
const WarrantyReport = lazy('WarrantyReport', () => import('../pages/reports/WarrantyReport.vue'));
const StockAdjustmentReport = lazy('StockAdjustmentReport', () => import('../pages/reports/StockAdjustmentReport.vue'));
const SerialSoldReport = lazy('SerialSoldReport', () => import('../pages/reports/SerialSoldReport.vue'));
const SerialAvailableReport = lazy('SerialAvailableReport', () => import('../pages/reports/SerialAvailableReport.vue'));
const SerialMovementReport = lazy('SerialMovementReport', () => import('../pages/reports/SerialMovementReport.vue'));
const SerialInventoryReport = lazy('SerialInventoryReport', () => import('../pages/reports/SerialInventoryReport.vue'));
const BatchRegisterReport = lazy('BatchRegisterReport', () => import('../pages/reports/BatchRegisterReport.vue'));
// One component serves all four payment reports; `meta.kind` selects the endpoint.
const PaymentsReport = lazy('PaymentsReport', () => import('../pages/reports/PaymentsReport.vue'));
const CustomersReport = lazy('CustomersReport', () => import('../pages/reports/CustomersReport.vue'));
const SuppliersReport = lazy('SuppliersReport', () => import('../pages/reports/SuppliersReport.vue'));
const CashRegisterReport = lazy('CashRegisterReport', () => import('../pages/reports/CashRegisterReport.vue'));
const AttendanceReport = lazy('AttendanceReport', () => import('../pages/reports/AttendanceReport.vue'));
const ServiceJobsReport = lazy('ServiceJobsReport', () => import('../pages/reports/ServiceJobsReport.vue'));
const ChecklistCompletionReport = lazy('ChecklistCompletionReport', () => import('../pages/reports/ChecklistCompletionReport.vue'));
const MaintenanceHistoryReport = lazy('MaintenanceHistoryReport', () => import('../pages/reports/MaintenanceHistoryReport.vue'));
const UsersReport = lazy('UsersReport', () => import('../pages/reports/UsersReport.vue'));
const LoginActivityReport = lazy('LoginActivityReport', () => import('../pages/reports/LoginActivityReport.vue'));
const ErrorLogsReport = lazy('ErrorLogsReport', () => import('../pages/reports/ErrorLogsReport.vue'));
const TopSellingProductsReport = lazy('TopSellingProductsReport', () => import('../pages/reports/TopSellingProductsReport.vue'));
const TopCustomersReport = lazy('TopCustomersReport', () => import('../pages/reports/TopCustomersReport.vue'));
const ExpensesReport = lazy('ExpensesReport', () => import('../pages/reports/ExpensesReport.vue'));
const DepositsReport = lazy('DepositsReport', () => import('../pages/reports/DepositsReport.vue'));
const SalesReport = lazy('SalesReport', () => import('../pages/reports/SalesReport.vue'));
const PurchasesReport = lazy('PurchasesReport', () => import('../pages/reports/PurchasesReport.vue'));
const LandedCostReport = lazy('LandedCostReport', () => import('../pages/reports/LandedCostReport.vue'));
const QuantityAlertsReport = lazy('QuantityAlertsReport', () => import('../pages/reports/QuantityAlertsReport.vue'));
const StockReport = lazy('StockReport', () => import('../pages/reports/StockReport.vue'));
const ProductReport = lazy('ProductReport', () => import('../pages/reports/ProductReport.vue'));
// Drill-downs reached from a row in the corresponding list report.
const CustomerDetailReport = lazy('CustomerDetailReport', () => import('../pages/reports/CustomerDetailReport.vue'));
const SupplierDetailReport = lazy('SupplierDetailReport', () => import('../pages/reports/SupplierDetailReport.vue'));
const ProductDetailReport = lazy('ProductDetailReport', () => import('../pages/reports/ProductDetailReport.vue'));
const StockDetailReport = lazy('StockDetailReport', () => import('../pages/reports/StockDetailReport.vue'));
const UserDetailReport = lazy('UserDetailReport', () => import('../pages/reports/UserDetailReport.vue'));
const BatchHistoryReport = lazy('BatchHistoryReport', () => import('../pages/reports/BatchHistoryReport.vue'));
const ProductSalesReport = lazy('ProductSalesReport', () => import('../pages/reports/ProductSalesReport.vue'));
const ProductsSoldSummaryReport = lazy('ProductsSoldSummaryReport', () => import('../pages/reports/ProductsSoldSummaryReport.vue'));
const ProductPurchasesReport = lazy('ProductPurchasesReport', () => import('../pages/reports/ProductPurchasesReport.vue'));
const SellerReport = lazy('SellerReport', () => import('../pages/reports/SellerReport.vue'));
const TransactionsReport = lazy('TransactionsReport', () => import('../pages/reports/TransactionsReport.vue'));
const InternalLocationReport = lazy('InternalLocationReport', () => import('../pages/reports/InternalLocationReport.vue'));
// One component for sales-by-category and sales-by-brand; `meta.kind` selects.
const SalesByGroupReport = lazy('SalesByGroupReport', () => import('../pages/reports/SalesByGroupReport.vue'));
const ProfitAndLossReport = lazy('ProfitAndLossReport', () => import('../pages/reports/ProfitAndLossReport.vue'));
const ProfitReport = lazy('ProfitReport', () => import('../pages/reports/profit/ProfitReport.vue'));
const AnalyticsReport = lazy('AnalyticsReport', () => import('../pages/reports/AnalyticsReport.vue'));
const Sales = lazy('Sales', () => import('../pages/sales/Sales.vue'));
const SaleDetails = lazy('SaleDetails', () => import('../pages/sales/SaleDetails.vue'));
const SaleForm = lazy('SaleForm', () => import('../pages/sales/SaleForm.vue'));
const Purchases = lazy('Purchases', () => import('../pages/purchases/Purchases.vue'));
const PurchaseDetails = lazy('PurchaseDetails', () => import('../pages/purchases/PurchaseDetails.vue'));
const PurchaseForm = lazy('PurchaseForm', () => import('../pages/purchases/PurchaseForm.vue'));
const ReorderSuggestions = lazy('ReorderSuggestions', () => import('../pages/purchases/ReorderSuggestions.vue'));
const Quotations = lazy('Quotations', () => import('../pages/quotations/Quotations.vue'));
const QuotationDetails = lazy('QuotationDetails', () => import('../pages/quotations/QuotationDetails.vue'));
const QuotationForm = lazy('QuotationForm', () => import('../pages/quotations/QuotationForm.vue'));
const SaleReturns = lazy('SaleReturns', () => import('../pages/sale_return/SaleReturns.vue'));
const SaleReturnDetails = lazy('SaleReturnDetails', () => import('../pages/sale_return/SaleReturnDetails.vue'));
const SaleReturnForm = lazy('SaleReturnForm', () => import('../pages/sale_return/SaleReturnForm.vue'));
const PurchaseReturns = lazy('PurchaseReturns', () => import('../pages/purchase_return/PurchaseReturns.vue'));
const PurchaseReturnDetails = lazy('PurchaseReturnDetails', () => import('../pages/purchase_return/PurchaseReturnDetails.vue'));
const PurchaseReturnForm = lazy('PurchaseReturnForm', () => import('../pages/purchase_return/PurchaseReturnForm.vue'));
const Adjustments = lazy('Adjustments', () => import('../pages/adjustment/Adjustments.vue'));
const AdjustmentDetails = lazy('AdjustmentDetails', () => import('../pages/adjustment/AdjustmentDetails.vue'));
const AdjustmentForm = lazy('AdjustmentForm', () => import('../pages/adjustment/AdjustmentForm.vue'));
const Transfers = lazy('Transfers', () => import('../pages/transfers/Transfers.vue'));
const TransferDetails = lazy('TransferDetails', () => import('../pages/transfers/TransferDetails.vue'));
const TransferForm = lazy('TransferForm', () => import('../pages/transfers/TransferForm.vue'));
const Products = lazy('Products', () => import('../pages/products/Products.vue'));
const ProductDetails = lazy('ProductDetails', () => import('../pages/products/ProductDetails.vue'));
const CountStock = lazy('CountStock', () => import('../pages/products/CountStock.vue'));
const Batches = lazy('Batches', () => import('../pages/products/Batches.vue'));
const ProductForm = lazy('ProductForm', () => import('../pages/products/ProductForm.vue'));
const ImportProducts = lazy('ImportProducts', () => import('../pages/products/ImportProducts.vue'));
const ImportProductsUpdate = lazy('ImportProductsUpdate', () => import('../pages/products/ImportProductsUpdate.vue'));
const OpeningStockImport = lazy('OpeningStockImport', () => import('../pages/products/OpeningStockImport.vue'));
const Barcode = lazy('Barcode', () => import('../pages/products/Barcode.vue'));
const Accounts = lazy('Accounts', () => import('../pages/accounting/Accounts.vue'));
const TransferMoney = lazy('TransferMoney', () => import('../pages/accounting/TransferMoney.vue'));
const Expenses = lazy('Expenses', () => import('../pages/expenses/Expenses.vue'));
const ExpenseForm = lazy('ExpenseForm', () => import('../pages/expenses/ExpenseForm.vue'));
const ExpenseCategories = lazy('ExpenseCategories', () => import('../pages/expenses/ExpenseCategories.vue'));
const Damages = lazy('Damages', () => import('../pages/damages/Damages.vue'));
const DamageForm = lazy('DamageForm', () => import('../pages/damages/DamageForm.vue'));
const Shipments = lazy('Shipments', () => import('../pages/sales/Shipments.vue'));
const OfflineSales = lazy('OfflineSales', () => import('../pages/sales/OfflineSales.vue'));
const CustomersWithoutLogin = lazy('CustomersWithoutLogin', () => import('../pages/people/CustomersWithoutLogin.vue'));
const CustomersWithLogin = lazy('CustomersWithLogin', () => import('../pages/people/CustomersWithLogin.vue'));
const LoyaltyRewards = lazy('LoyaltyRewards', () => import('../pages/loyalty/LoyaltyRewards.vue'));
const RealTimeSalesCounter = lazy('RealTimeSalesCounter', () => import('../pages/RealTimeSalesCounter.vue'));
const CustomerDisplaySetup = lazy('CustomerDisplaySetup', () => import('../pages/CustomerDisplaySetup.vue'));
const PosReceipt = lazy('PosReceipt', () => import('../pages/settings/PosReceipt.vue'));
const ModuleSettings = lazy('ModuleSettings', () => import('../pages/settings/ModuleSettings.vue'));
const Modules = lazy('Modules', () => import('../pages/settings/Modules.vue'));
const UpdateSettings = lazy('UpdateSettings', () => import('../pages/settings/UpdateSettings.vue'));
const SystemSettings = lazy('SystemSettings', () => import('../pages/settings/SystemSettings.vue'));
const QuickBooksSync = lazy('QuickBooksSync', () => import('../pages/settings/QuickBooksSync.vue'));
const ZatcaSettings = lazy('ZatcaSettings', () => import('../pages/settings/ZatcaSettings.vue'));
const WooCommerceSettings = lazy('WooCommerceSettings', () => import('../pages/settings/WooCommerceSettings.vue'));
const WooCommerceGuidePage = lazy('WooCommerceGuidePage', () => import('../pages/settings/woocommerce/WooCommerceGuidePage.vue'));
// Integrations platform
const IntegrationsHub = lazy('IntegrationsHub', () => import('../pages/integrations/IntegrationsHub.vue'));
const SlackSettings = lazy('SlackSettings', () => import('../pages/integrations/SlackSettings.vue'));
const TelegramSettings = lazy('TelegramSettings', () => import('../pages/integrations/TelegramSettings.vue'));
const WhatsAppSettings = lazy('WhatsAppSettings', () => import('../pages/integrations/WhatsAppSettings.vue'));
const SallaSettings = lazy('SallaSettings', () => import('../pages/integrations/SallaSettings.vue'));
const XeroSettings = lazy('XeroSettings', () => import('../pages/integrations/XeroSettings.vue'));
const PrestashopSettings = lazy('PrestashopSettings', () => import('../pages/integrations/PrestashopSettings.vue'));
const GoogleSheetsSettings = lazy('GoogleSheetsSettings', () => import('../pages/integrations/GoogleSheetsSettings.vue'));
const MailchimpSettings = lazy('MailchimpSettings', () => import('../pages/integrations/MailchimpSettings.vue'));
const JumiaSettings = lazy('JumiaSettings', () => import('../pages/integrations/JumiaSettings.vue'));
const DueAssets = lazy('DueAssets', () => import('../pages/assets/DueAssets.vue'));
const Subscriptions = lazy('Subscriptions', () => import('../pages/subscriptions/Subscriptions.vue'));
const SubscriptionForm = lazy('SubscriptionForm', () => import('../pages/subscriptions/SubscriptionForm.vue'));
const SubscriptionDetails = lazy('SubscriptionDetails', () => import('../pages/subscriptions/SubscriptionDetails.vue'));
const BookingCalendar = lazy('BookingCalendar', () => import('../pages/bookings/BookingCalendar.vue'));
const GroupPermissions = lazy('GroupPermissions', () => import('../pages/permissions/GroupPermissions.vue'));
const PermissionForm = lazy('PermissionForm', () => import('../pages/permissions/PermissionForm.vue'));
const StoreSettings = lazy('StoreSettings', () => import('../pages/store/StoreSettings.vue'));
const StorePaymentGateway = lazy('StorePaymentGateway', () => import('../pages/store/StorePaymentGateway.vue'));
const StoreOrders = lazy('StoreOrders', () => import('../pages/store/Orders.vue'));
const StoreOrderShow = lazy('StoreOrderShow', () => import('../pages/store/OrderShow.vue'));
const StoreCollections = lazy('StoreCollections', () => import('../pages/store/Collections.vue'));
const StoreCollectionForm = lazy('StoreCollectionForm', () => import('../pages/store/CollectionForm.vue'));
const StoreMessages = lazy('StoreMessages', () => import('../pages/store/Messages.vue'));
const StoreInviteCodes = lazy('StoreInviteCodes', () => import('../pages/store/InviteCodes.vue'));
const StoreFlashSales = lazy('StoreFlashSales', () => import('../pages/store/FlashSales.vue'));
const StoreSubscribers = lazy('StoreSubscribers', () => import('../pages/store/Subscribers.vue'));
const StoreQuoteRequests = lazy('StoreQuoteRequests', () => import('../pages/store/QuoteRequests.vue'));
const StoreReviews = lazy('StoreReviews', () => import('../pages/store/Reviews.vue'));
const StoreReturns = lazy('StoreReturns', () => import('../pages/store/Returns.vue'));
const StoreQuestions = lazy('StoreQuestions', () => import('../pages/store/Questions.vue'));
const StoreMenus = lazy('StoreMenus', () => import('../pages/store/Menus.vue'));
const StoreCoupons = lazy('StoreCoupons', () => import('../pages/store/Coupons.vue'));
const StoreTaxRates = lazy('StoreTaxRates', () => import('../pages/store/TaxRates.vue'));
const StorePendingCustomers = lazy('StorePendingCustomers', () => import('../pages/store/PendingCustomers.vue'));
const StorePages = lazy('StorePages', () => import('../pages/store/Pages.vue'));
const StorePopups = lazy('StorePopups', () => import('../pages/store/Popups.vue'));
const StoreShippingMethods = lazy('StoreShippingMethods', () => import('../pages/store/ShippingMethods.vue'));
const StoreShippingZones = lazy('StoreShippingZones', () => import('../pages/store/ShippingZones.vue'));
const StoreBanners = lazy('StoreBanners', () => import('../pages/store/Banners.vue'));
const StoreBannerForm = lazy('StoreBannerForm', () => import('../pages/store/BannerForm.vue'));
const StoreLanguages = lazy('StoreLanguages', () => import('../pages/store/StoreLanguages.vue'));
const StoreTranslations = lazy('StoreTranslations', () => import('../pages/store/StoreTranslations.vue'));
const Properties = lazy('Properties', () => import('../pages/realestate/Properties.vue'));
const PropertyForm = lazy('PropertyForm', () => import('../pages/realestate/PropertyForm.vue'));
const PropertyCategories = lazy('PropertyCategories', () => import('../pages/realestate/PropertyCategories.vue'));
const PropertyInquiries = lazy('PropertyInquiries', () => import('../pages/realestate/PropertyInquiries.vue'));
const AiReports = lazy('AiReports', () => import('../pages/reports/AiReports.vue'));
const Sales3DDashboard = lazy('Sales3DDashboard', () => import('../pages/dashboard/Sales3DDashboard.vue'));
const AccountingDashboard = lazy('AccountingDashboard', () => import('../pages/accounting/v2/AccountingDashboard.vue'));
const ChartOfAccounts = lazy('ChartOfAccounts', () => import('../pages/accounting/v2/ChartOfAccounts.vue'));
const JournalEntries = lazy('JournalEntries', () => import('../pages/accounting/v2/JournalEntries.vue'));
const TrialBalance = lazy('TrialBalance', () => import('../pages/accounting/v2/TrialBalance.vue'));
const ProfitAndLossV2 = lazy('ProfitAndLossV2', () => import('../pages/accounting/v2/ProfitAndLoss.vue'));
const BalanceSheet = lazy('BalanceSheet', () => import('../pages/accounting/v2/BalanceSheet.vue'));
const TaxReportV2 = lazy('TaxReportV2', () => import('../pages/accounting/v2/TaxReport.vue'));
const MeetingDashboard = lazy('MeetingDashboard', () => import('../pages/meeting/MeetingDashboard.vue'));
const MeetingDetails = lazy('MeetingDetails', () => import('../pages/meeting/MeetingDetails.vue'));
const MeetingCalendar = lazy('MeetingCalendar', () => import('../pages/meeting/MeetingCalendar.vue'));
const MeetingReports = lazy('MeetingReports', () => import('../pages/meeting/MeetingReports.vue'));
const WalletDashboard = lazy('WalletDashboard', () => import('../pages/ewallet/WalletDashboard.vue'));
const WalletItems = lazy('WalletItems', () => import('../pages/ewallet/WalletItems.vue'));
const WalletSettings = lazy('WalletSettings', () => import('../pages/ewallet/WalletSettings.vue'));
const RecruitDashboard = lazy('RecruitDashboard', () => import('../pages/recruit/RecruitDashboard.vue'));
const RecruitJobs = lazy('RecruitJobs', () => import('../pages/recruit/RecruitJobs.vue'));
const RecruitCategories = lazy('RecruitCategories', () => import('../pages/recruit/RecruitCategories.vue'));
const RecruitCandidates = lazy('RecruitCandidates', () => import('../pages/recruit/RecruitCandidates.vue'));
const RecruitApplications = lazy('RecruitApplications', () => import('../pages/recruit/RecruitApplications.vue'));
const RecruitInterviews = lazy('RecruitInterviews', () => import('../pages/recruit/RecruitInterviews.vue'));
const RecruitReports = lazy('RecruitReports', () => import('../pages/recruit/RecruitReports.vue'));
const CommissionPrograms = lazy('CommissionPrograms', () => import('../pages/commissions/CommissionPrograms.vue'));
const SalesAgents = lazy('SalesAgents', () => import('../pages/commissions/SalesAgents.vue'));
const CommissionRules = lazy('CommissionRules', () => import('../pages/commissions/CommissionRules.vue'));
const CommissionReceipts = lazy('CommissionReceipts', () => import('../pages/commissions/CommissionReceipts.vue'));
const CommissionReport = lazy('CommissionReport', () => import('../pages/commissions/CommissionReport.vue'));
const ServiceJobsList = lazy('ServiceJobsList', () => import('../pages/service/ServiceJobsList.vue'));
const ServiceJobForm = lazy('ServiceJobForm', () => import('../pages/service/ServiceJobForm.vue'));
const ServiceJobDetails = lazy('ServiceJobDetails', () => import('../pages/service/ServiceJobDetails.vue'));
const ServiceTechnicians = lazy('ServiceTechnicians', () => import('../pages/service/ServiceTechnicians.vue'));
const ServiceChecklistCategories = lazy('ServiceChecklistCategories', () => import('../pages/service/ServiceChecklistCategories.vue'));
const ServiceChecklists = lazy('ServiceChecklists', () => import('../pages/service/ServiceChecklists.vue'));
const CustomerMaintenanceHistory = lazy('CustomerMaintenanceHistory', () => import('../pages/service/CustomerMaintenanceHistory.vue'));
const ImportCustomers = lazy('ImportCustomers', () => import('../pages/people/ImportCustomers.vue'));
const ImportSuppliers = lazy('ImportSuppliers', () => import('../pages/people/ImportSuppliers.vue'));
const ImportPurchases = lazy('ImportPurchases', () => import('../pages/purchases/ImportPurchases.vue'));
const ImportSales = lazy('ImportSales', () => import('../pages/sales/ImportSales.vue'));
const PaymentMethods = lazy('PaymentMethods', () => import('../pages/settings/PaymentMethods.vue'));
const PaymentGateway = lazy('PaymentGateway', () => import('../pages/settings/PaymentGateway.vue'));
const MailSettings = lazy('MailSettings', () => import('../pages/settings/MailSettings.vue'));
const LoginDevices = lazy('LoginDevices', () => import('../pages/settings/LoginDevices.vue'));
const SystemHealth = lazy('SystemHealth', () => import('../pages/settings/SystemHealth.vue'));
const AuditLog = lazy('AuditLog', () => import('../pages/settings/AuditLog.vue'));
const AiAssistant = lazy('AiAssistant', () => import('../pages/ai/AiAssistant.vue'));
const SmsTemplates = lazy('SmsTemplates', () => import('../pages/settings/SmsTemplates.vue'));
const EmailTemplates = lazy('EmailTemplates', () => import('../pages/settings/EmailTemplates.vue'));
const CustomFields = lazy('CustomFields', () => import('../pages/settings/CustomFields.vue'));
const Backup = lazy('Backup', () => import('../pages/settings/Backup.vue'));
const PosSettings = lazy('PosSettings', () => import('../pages/settings/PosSettings.vue'));
const AppearanceSettings = lazy('AppearanceSettings', () => import('../pages/settings/AppearanceSettings.vue'));
const PwaSettings = lazy('PwaSettings', () => import('../pages/settings/PwaSettings.vue'));
const MobileAppSettings = lazy('MobileAppSettings', () => import('../pages/settings/MobileAppSettings.vue'));
const SmsSettings = lazy('SmsSettings', () => import('../pages/settings/SmsSettings.vue'));
const Languages = lazy('Languages', () => import('../pages/settings/Languages.vue'));
const TranslationsView = lazy('TranslationsView', () => import('../pages/settings/TranslationsView.vue'));
const Webhooks = lazy('Webhooks', () => import('../pages/settings/Webhooks.vue'));
const SerialNumbers = lazy('SerialNumbers', () => import('../pages/serial_numbers/SerialNumbers.vue'));
const SerialNumberDetails = lazy('SerialNumberDetails', () => import('../pages/serial_numbers/SerialNumberDetails.vue'));
const Deposits = lazy('Deposits', () => import('../pages/deposits/Deposits.vue'));
const DepositForm = lazy('DepositForm', () => import('../pages/deposits/DepositForm.vue'));
const DepositCategories = lazy('DepositCategories', () => import('../pages/deposits/DepositCategories.vue'));
const CashFlowReport = lazy('CashFlowReport', () => import('../pages/reports/CashFlowReport.vue'));
const StockTransferReport = lazy('StockTransferReport', () => import('../pages/reports/StockTransferReport.vue'));
const ReturnRatioReport = lazy('ReturnRatioReport', () => import('../pages/reports/ReturnRatioReport.vue'));
const StockAgingReport = lazy('StockAgingReport', () => import('../pages/reports/StockAgingReport.vue'));
const InventoryValuationSummaryReport = lazy('InventoryValuationSummaryReport', () => import('../pages/reports/InventoryValuationSummaryReport.vue'));
const StockInventoryValuationReport = lazy('StockInventoryValuationReport', () => import('../pages/reports/StockInventoryValuationReport.vue'));
const WarehouseReport = lazy('WarehouseReport', () => import('../pages/reports/WarehouseReport.vue'));
// --- People (Wave C) ---
const Customers = lazy('Customers', () => import('../pages/people/Customers.vue'));
const CustomerForm = lazy('CustomerForm', () => import('../pages/people/CustomerForm.vue'));
const Suppliers = lazy('Suppliers', () => import('../pages/people/Suppliers.vue'));
const SupplierForm = lazy('SupplierForm', () => import('../pages/people/SupplierForm.vue'));
const SupplierDetails = lazy('SupplierDetails', () => import('../pages/people/SupplierDetails.vue'));
const Users = lazy('Users', () => import('../pages/people/Users.vue'));
const UserForm = lazy('UserForm', () => import('../pages/people/UserForm.vue'));
// --- HRM (Wave C) ---
const Companies = lazy('Companies', () => import('../pages/hrm/Companies.vue'));
const Departments = lazy('Departments', () => import('../pages/hrm/Departments.vue'));
const Designations = lazy('Designations', () => import('../pages/hrm/Designations.vue'));
const Holidays = lazy('Holidays', () => import('../pages/hrm/Holidays.vue'));
const OfficeShifts = lazy('OfficeShifts', () => import('../pages/hrm/OfficeShifts.vue'));
// --- Assets (Wave C) ---
const Assets = lazy('Assets', () => import('../pages/assets/Assets.vue'));
const AssetForm = lazy('AssetForm', () => import('../pages/assets/AssetForm.vue'));
const AssetCategories = lazy('AssetCategories', () => import('../pages/assets/AssetCategories.vue'));
const AssetsDashboard = lazy('AssetsDashboard', () => import('../pages/assets/AssetsDashboard.vue'));
const AssetDetails = lazy('AssetDetails', () => import('../pages/assets/AssetDetails.vue'));
const AssetAssignments = lazy('AssetAssignments', () => import('../pages/assets/AssetAssignments.vue'));
const AssetMaintenance = lazy('AssetMaintenance', () => import('../pages/assets/AssetMaintenance.vue'));
const AssetTransfers = lazy('AssetTransfers', () => import('../pages/assets/AssetTransfers.vue'));
const AssetDepreciation = lazy('AssetDepreciation', () => import('../pages/assets/AssetDepreciation.vue'));
const AssetReports = lazy('AssetReports', () => import('../pages/assets/AssetReports.vue'));

// --- Shopify Integration ---
const ShopifyDashboard = lazy('ShopifyDashboard', () => import('../pages/shopify/ShopifyDashboard.vue'));
const ShopifyStores = lazy('ShopifyStores', () => import('../pages/shopify/ShopifyStores.vue'));
const ShopifyStoreDetails = lazy('ShopifyStoreDetails', () => import('../pages/shopify/ShopifyStoreDetails.vue'));
const ShopifySyncCenter = lazy('ShopifySyncCenter', () => import('../pages/shopify/ShopifySyncCenter.vue'));
const ShopifyMappings = lazy('ShopifyMappings', () => import('../pages/shopify/ShopifyMappings.vue'));
const ShopifyLogs = lazy('ShopifyLogs', () => import('../pages/shopify/ShopifyLogs.vue'));
const ShopifyGuidePage = lazy('ShopifyGuidePage', () => import('../pages/shopify/ShopifyGuidePage.vue'));

// --- Advanced Manufacturing MRP ---
const MrpDashboard = lazy('MrpDashboard', () => import('../pages/manufacturing/MrpDashboard.vue'));
const MrpProductionOrders = lazy('MrpProductionOrders', () => import('../pages/manufacturing/ProductionOrders.vue'));
const MrpProductionOrderForm = lazy('MrpProductionOrderForm', () => import('../pages/manufacturing/ProductionOrderForm.vue'));
const MrpProductionOrderDetails = lazy('MrpProductionOrderDetails', () => import('../pages/manufacturing/ProductionOrderDetails.vue'));
const MrpWorkOrders = lazy('MrpWorkOrders', () => import('../pages/manufacturing/WorkOrders.vue'));
const MrpBoms = lazy('MrpBoms', () => import('../pages/manufacturing/Boms.vue'));
const MrpBomForm = lazy('MrpBomForm', () => import('../pages/manufacturing/BomForm.vue'));
const MrpWorkCenters = lazy('MrpWorkCenters', () => import('../pages/manufacturing/WorkCenters.vue'));
const MrpQualityChecks = lazy('MrpQualityChecks', () => import('../pages/manufacturing/QualityChecks.vue'));
const MrpPlanning = lazy('MrpPlanning', () => import('../pages/manufacturing/MrpPlanning.vue'));
const MrpReports = lazy('MrpReports', () => import('../pages/manufacturing/MrpReports.vue'));
// --- Bookings (Wave C) ---
const Bookings = lazy('Bookings', () => import('../pages/bookings/Bookings.vue'));
const BookingForm = lazy('BookingForm', () => import('../pages/bookings/BookingForm.vue'));
const KitchenDisplay = lazy('KitchenDisplay', () => import('../pages/kitchen/KitchenDisplay.vue'));
const KitchenReport = lazy('KitchenReport', () => import('../pages/kitchen/KitchenReport.vue'));
const Trays = lazy('Trays', () => import('../pages/bookings/Trays.vue'));
const Meetings = lazy('Meetings', () => import('../pages/meeting/Meetings.vue'));
// --- Knowledge base (Wave C) ---
const KnowledgeBase = lazy('KnowledgeBase', () => import('../pages/knowledge_base/KnowledgeBase.vue'));
const KnowledgeBaseGroups = lazy('KnowledgeBaseGroups', () => import('../pages/knowledge_base/KnowledgeBaseGroups.vue'));
const KnowledgeBaseArticle = lazy('KnowledgeBaseArticle', () => import('../pages/knowledge_base/KnowledgeBaseArticle.vue'));
const KnowledgeBaseArticleForm = lazy('KnowledgeBaseArticleForm', () => import('../pages/knowledge_base/KnowledgeBaseArticleForm.vue'));
// --- Client Portal section ---
const PortalDashboard = lazy('PortalDashboard', () => import('../pages/client_portal/PortalDashboard.vue'));
const PortalAccounts = lazy('PortalAccounts', () => import('../pages/client_portal/PortalAccounts.vue'));
const PortalDeletionLog = lazy('PortalDeletionLog', () => import('../pages/client_portal/PortalDeletionLog.vue'));
const PortalSettings = lazy('PortalSettings', () => import('../pages/client_portal/PortalSettings.vue'));
const PortalLanguages = lazy('PortalLanguages', () => import('../pages/client_portal/PortalLanguages.vue'));
const PortalTranslations = lazy('PortalTranslations', () => import('../pages/client_portal/PortalTranslations.vue'));
const Profile = lazy('Profile', () => import('../pages/profile/Profile.vue'));
const CustomerDetails = lazy('CustomerDetails', () => import('../pages/people/CustomerDetails.vue'));
const CustomerLedger = lazy('CustomerLedger', () => import('../pages/people/CustomerLedger.vue'));
// --- Projects (Wave C) ---
const Projects = lazy('Projects', () => import('../pages/projects/Projects.vue'));
const ProjectForm = lazy('ProjectForm', () => import('../pages/projects/ProjectForm.vue'));
const Tasks = lazy('Tasks', () => import('../pages/tasks/Tasks.vue'));
const TaskForm = lazy('TaskForm', () => import('../pages/tasks/TaskForm.vue'));
const ProjectsDashboard = lazy('ProjectsDashboard', () => import('../pages/projects/ProjectsDashboard.vue'));
const TaskBoard = lazy('TaskBoard', () => import('../pages/projects/TaskBoard.vue'));
const ProjectMilestones = lazy('ProjectMilestones', () => import('../pages/projects/Milestones.vue'));
const ProjectTimesheets = lazy('ProjectTimesheets', () => import('../pages/projects/Timesheets.vue'));
const ProjectReports = lazy('ProjectReports', () => import('../pages/projects/ProjectReports.vue'));
const Promotions = lazy('Promotions', () => import('../pages/promotions/Promotions.vue'));
const PromotionForm = lazy('PromotionForm', () => import('../pages/promotions/PromotionForm.vue'));
const PromotionUsages = lazy('PromotionUsages', () => import('../pages/promotions/PromotionUsages.vue'));
const Contracts = lazy('Contracts', () => import('../pages/contracts/Contracts.vue'));
const ContractForm = lazy('ContractForm', () => import('../pages/contracts/ContractForm.vue'));
const ContractView = lazy('ContractView', () => import('../pages/contracts/ContractView.vue'));
const DocumentArchive = lazy('DocumentArchive', () => import('../pages/documents/DocumentArchive.vue'));
const FleetDashboard = lazy('FleetDashboard', () => import('../pages/fleet/FleetDashboard.vue'));
const Vehicles = lazy('Vehicles', () => import('../pages/fleet/Vehicles.vue'));
const VehicleForm = lazy('VehicleForm', () => import('../pages/fleet/VehicleForm.vue'));
const VehicleDetails = lazy('VehicleDetails', () => import('../pages/fleet/VehicleDetails.vue'));
const FleetMaintenance = lazy('FleetMaintenance', () => import('../pages/fleet/Maintenance.vue'));
const FleetFuelLogs = lazy('FleetFuelLogs', () => import('../pages/fleet/FuelLogs.vue'));
const FleetAssignments = lazy('FleetAssignments', () => import('../pages/fleet/Assignments.vue'));
const FleetReports = lazy('FleetReports', () => import('../pages/fleet/FleetReports.vue'));
const RentalDashboard = lazy('RentalDashboard', () => import('../pages/rental/RentalDashboard.vue'));
const RentalItems = lazy('RentalItems', () => import('../pages/rental/RentalItems.vue'));
const RentalItemForm = lazy('RentalItemForm', () => import('../pages/rental/RentalItemForm.vue'));
const RentalItemDetails = lazy('RentalItemDetails', () => import('../pages/rental/RentalItemDetails.vue'));
const RentalCustomers = lazy('RentalCustomers', () => import('../pages/rental/RentalCustomers.vue'));
const RentalCustomerForm = lazy('RentalCustomerForm', () => import('../pages/rental/RentalCustomerForm.vue'));
const RentalCustomerDetails = lazy('RentalCustomerDetails', () => import('../pages/rental/RentalCustomerDetails.vue'));
const RentalContracts = lazy('RentalContracts', () => import('../pages/rental/RentalContracts.vue'));
const RentalContractForm = lazy('RentalContractForm', () => import('../pages/rental/RentalContractForm.vue'));
const RentalContractDetails = lazy('RentalContractDetails', () => import('../pages/rental/RentalContractDetails.vue'));
const RentalAgreementTemplates = lazy('RentalAgreementTemplates', () => import('../pages/rental/RentalAgreementTemplates.vue'));
const RentalAvailability = lazy('RentalAvailability', () => import('../pages/rental/RentalAvailability.vue'));
const RentalCalendar = lazy('RentalCalendar', () => import('../pages/rental/RentalCalendar.vue'));
const RentalReservations = lazy('RentalReservations', () => import('../pages/rental/RentalReservations.vue'));
const RentalReservationForm = lazy('RentalReservationForm', () => import('../pages/rental/RentalContractForm.vue'));
const RentalReservationDetails = lazy('RentalReservationDetails', () => import('../pages/rental/RentalReservationDetails.vue'));
const RentalInvoices = lazy('RentalInvoices', () => import('../pages/rental/RentalInvoices.vue'));
const RentalInvoiceDetails = lazy('RentalInvoiceDetails', () => import('../pages/rental/RentalInvoiceDetails.vue'));
const RentalPayments = lazy('RentalPayments', () => import('../pages/rental/RentalPayments.vue'));
const RentalHandover = lazy('RentalHandover', () => import('../pages/rental/RentalHandover.vue'));
const RentalMaintenance = lazy('RentalMaintenance', () => import('../pages/rental/RentalMaintenance.vue'));
const RentalExpenses = lazy('RentalExpenses', () => import('../pages/rental/RentalExpenses.vue'));
const RentalReports = lazy('RentalReports', () => import('../pages/rental/RentalReports.vue'));
const RentalStatement = lazy('RentalStatement', () => import('../pages/rental/RentalStatement.vue'));
const RentalCategories = lazy('RentalCategories', () => import('../pages/rental/RentalCategories.vue'));
const RentalSettings = lazy('RentalSettings', () => import('../pages/rental/RentalSettings.vue'));
// CRM module
const CrmDashboard = lazy('CrmDashboard', () => import('../pages/crm/CrmDashboard.vue'));
const CrmLeads = lazy('CrmLeads', () => import('../pages/crm/CrmLeads.vue'));
const CrmLeadForm = lazy('CrmLeadForm', () => import('../pages/crm/CrmLeadForm.vue'));
const CrmLeadDetails = lazy('CrmLeadDetails', () => import('../pages/crm/CrmLeadDetails.vue'));
const CrmLeadImport = lazy('CrmLeadImport', () => import('../pages/crm/CrmLeadImport.vue'));
const CrmSettings = lazy('CrmSettings', () => import('../pages/crm/CrmSettings.vue'));
const CrmPipelineBoard = lazy('CrmPipelineBoard', () => import('../pages/crm/CrmPipelineBoard.vue'));
const CrmDeals = lazy('CrmDeals', () => import('../pages/crm/CrmDeals.vue'));
const CrmDealForm = lazy('CrmDealForm', () => import('../pages/crm/CrmDealForm.vue'));
const CrmDealDetails = lazy('CrmDealDetails', () => import('../pages/crm/CrmDealDetails.vue'));
const CrmMyDay = lazy('CrmMyDay', () => import('../pages/crm/CrmMyDay.vue'));
const CrmActivities = lazy('CrmActivities', () => import('../pages/crm/CrmActivities.vue'));
const CrmCustomers = lazy('CrmCustomers', () => import('../pages/crm/CrmCustomers.vue'));
const CrmCustomer360 = lazy('CrmCustomer360', () => import('../pages/crm/CrmCustomer360.vue'));
const CrmInbox = lazy('CrmInbox', () => import('../pages/crm/CrmInbox.vue'));
const CrmWebForms = lazy('CrmWebForms', () => import('../pages/crm/CrmWebForms.vue'));
const CrmReports = lazy('CrmReports', () => import('../pages/crm/CrmReports.vue'));
const CrmTargets = lazy('CrmTargets', () => import('../pages/crm/CrmTargets.vue'));
const CourierAccounts = lazy('CourierAccounts', () => import('../pages/couriers/CourierAccounts.vue'));
const CourierSettings = lazy('CourierSettings', () => import('../pages/couriers/CourierSettings.vue'));
const CourierShipments = lazy('CourierShipments', () => import('../pages/couriers/CourierShipments.vue'));
const CourierReports = lazy('CourierReports', () => import('../pages/couriers/CourierReports.vue'));
const CourierLogs = lazy('CourierLogs', () => import('../pages/couriers/CourierLogs.vue'));
const CourierGuide = lazy('CourierGuide', () => import('../pages/couriers/CourierGuide.vue'));
const HospitalDashboard = lazy('HospitalDashboard', () => import('../pages/hospital/HospitalDashboard.vue'));
const HospitalPatients = lazy('HospitalPatients', () => import('../pages/hospital/Patients.vue'));
const PatientForm = lazy('PatientForm', () => import('../pages/hospital/PatientForm.vue'));
const PatientDetails = lazy('PatientDetails', () => import('../pages/hospital/PatientDetails.vue'));
const HospitalDoctors = lazy('HospitalDoctors', () => import('../pages/hospital/Doctors.vue'));
const HospitalDepartments = lazy('HospitalDepartments', () => import('../pages/hospital/HospitalDepartments.vue'));
const HospitalAppointments = lazy('HospitalAppointments', () => import('../pages/hospital/Appointments.vue'));
const HospitalVisits = lazy('HospitalVisits', () => import('../pages/hospital/Visits.vue'));
const HospitalVisitForm = lazy('HospitalVisitForm', () => import('../pages/hospital/VisitForm.vue'));
const HospitalWards = lazy('HospitalWards', () => import('../pages/hospital/Wards.vue'));
const HospitalAdmissions = lazy('HospitalAdmissions', () => import('../pages/hospital/Admissions.vue'));
const HospitalLabTests = lazy('HospitalLabTests', () => import('../pages/hospital/LabTests.vue'));
const HospitalLabOrders = lazy('HospitalLabOrders', () => import('../pages/hospital/LabOrders.vue'));
const HospitalInvoices = lazy('HospitalInvoices', () => import('../pages/hospital/Invoices.vue'));
const HospitalReports = lazy('HospitalReports', () => import('../pages/hospital/HospitalReports.vue'));
const SchoolDashboard = lazy('SchoolDashboard', () => import('../pages/school/SchoolDashboard.vue'));
const SchoolStudents = lazy('SchoolStudents', () => import('../pages/school/Students.vue'));
const SchoolStudentForm = lazy('SchoolStudentForm', () => import('../pages/school/StudentForm.vue'));
const SchoolStudentDetails = lazy('SchoolStudentDetails', () => import('../pages/school/StudentDetails.vue'));
const SchoolTeachers = lazy('SchoolTeachers', () => import('../pages/school/Teachers.vue'));
const SchoolAcademics = lazy('SchoolAcademics', () => import('../pages/school/Academics.vue'));
const SchoolEnrollments = lazy('SchoolEnrollments', () => import('../pages/school/Enrollments.vue'));
const SchoolAttendance = lazy('SchoolAttendance', () => import('../pages/school/Attendance.vue'));
const SchoolTimetable = lazy('SchoolTimetable', () => import('../pages/school/Timetable.vue'));
const SchoolExams = lazy('SchoolExams', () => import('../pages/school/Exams.vue'));
const SchoolFees = lazy('SchoolFees', () => import('../pages/school/Fees.vue'));
const SchoolReports = lazy('SchoolReports', () => import('../pages/school/SchoolReports.vue'));
const Employees = lazy('Employees', () => import('../pages/hrm/Employees.vue'));
const EmployeeForm = lazy('EmployeeForm', () => import('../pages/hrm/EmployeeForm.vue'));
const EmployeeDetails = lazy('EmployeeDetails', () => import('../pages/hrm/EmployeeDetails.vue'));
const Attendance = lazy('Attendance', () => import('../pages/hrm/Attendance.vue'));
const Leaves = lazy('Leaves', () => import('../pages/hrm/Leaves.vue'));
const LeaveTypes = lazy('LeaveTypes', () => import('../pages/hrm/LeaveTypes.vue'));
const Payrolls = lazy('Payrolls', () => import('../pages/hrm/Payrolls.vue'));
const MarketingDashboard = lazy('MarketingDashboard', () => import('../pages/marketing/MarketingDashboard.vue'));
const Campaigns = lazy('Campaigns', () => import('../pages/marketing/Campaigns.vue'));
const CampaignForm = lazy('CampaignForm', () => import('../pages/marketing/CampaignForm.vue'));
const CampaignDetails = lazy('CampaignDetails', () => import('../pages/marketing/CampaignDetails.vue'));
const Segments = lazy('Segments', () => import('../pages/marketing/Segments.vue'));
const MarketingTemplates = lazy('MarketingTemplates', () => import('../pages/marketing/Templates.vue'));
const MarketingReports = lazy('MarketingReports', () => import('../pages/marketing/MarketingReports.vue'));
const MarketingSettings = lazy('MarketingSettings', () => import('../pages/marketing/MarketingSettings.vue'));

// `permission` values come from the legacy sidebar's own gates (config/menu.js).
const routes = [
    // Diagnostic page — outside AdminLayout, no auth, no data, no charts.
    { path: '/ping', name: 'ping', component: Ping, meta: { skipAuth: true, title: 'Ping' } },
    // POS 1:1 port — fullscreen, outside AdminLayout. Deliberately absent
    // from the sidebar until the parity pass signs off (legacy stays live).
    { path: '/pos', name: 'pos', component: PosPage, meta: { title: 'POS', titleKey: 'Pos', permission: 'Pos_view' } },
    // Mock POS shell for the System Settings → POS Theme preview iframe —
    // fullscreen, sample data only.
    { path: '/pos-theme-preview', name: 'pos-theme-preview', component: PosThemePreviewPage, meta: { title: 'POS Theme', titleKey: 'PosTheme_Title', permission: ['setting_system', 'pos_settings'] } },
    {
        path: '/',
        component: AdminLayout,
        children: [
            { path: '', redirect: '/dashboard' },
            { path: 'dashboard', name: 'dashboard', component: Dashboard, meta: { title: 'Dashboard', titleKey: 'dashboard', permission: 'dashboard' } },
            { path: 'brands', name: 'brands', component: Brands, meta: { title: 'Brands', titleKey: 'Brands', permission: 'brand' } },
            { path: 'categories', name: 'categories', component: Categories, meta: { title: 'Categories', titleKey: 'Categories_Count', permission: 'category' } },
            { path: 'subcategories', name: 'subcategories', component: SubCategories, meta: { title: 'SubCategories', titleKey: 'Page_SubCategories', permission: 'subcategory' } },
            { path: 'units', name: 'units', component: Units, meta: { title: 'Units', titleKey: 'Page_Units', permission: 'unit' } },
            { path: 'size-guides', name: 'size-guides', component: SizeGuides, meta: { title: 'Size Guides', titleKey: 'Size_Guides', permission: 'size_guides' } },
            { path: 'currencies', name: 'currencies', component: Currencies, meta: { title: 'Currencies', titleKey: 'Page_Currencies', permission: 'currency' } },
            { path: 'warehouses', name: 'warehouses', component: Warehouses, meta: { title: 'Warehouses', titleKey: 'Promo_Warehouses', permission: 'warehouse' } },
            // Reports
            { path: 'reports/dead-stock', name: 'dead-stock-report', component: DeadStockReport, meta: { title: 'Dead Stock Report', titleKey: 'Dead_Stock_Report', permission: 'Dead_Stock_Report' } },
            { path: 'reports/zero-sales', name: 'zero-sales-report', component: ZeroSalesReport, meta: { title: 'Zero Sales Products', titleKey: 'Page_Zero_Sales_Products', permission: 'zeroSalesProducts' } },
            { path: 'reports/negative-stock', name: 'negative-stock-report', component: NegativeStockReport, meta: { title: 'Negative Stock', titleKey: 'Page_Negative_Stock', permission: 'negative_stock_report' } },
            { path: 'reports/expiry', name: 'expiry-report', component: ExpiryReport, meta: { title: 'Expiry Report', titleKey: 'Expiry_Report', permission: 'expiry_report' } },
            { path: 'reports/discount-summary', name: 'discount-summary-report', component: DiscountSummaryReport, meta: { title: 'Discount Summary', titleKey: 'Page_Discount_Summary', permission: 'discount_summary_report' } },
            { path: 'reports/tax-summary', name: 'tax-summary-report', component: TaxSummaryReport, meta: { title: 'Tax Summary', titleKey: 'Tax_Summary_Link', permission: 'tax_summary_report' } },
            { path: 'reports/loyalty-points', name: 'loyalty-points-report', component: LoyaltyPointsReport, meta: { title: 'Loyalty Points', titleKey: 'Loyalty_Points', permission: 'customer_loyalty_points_report' } },
            { path: 'reports/inactive-customers', name: 'inactive-customers-report', component: InactiveCustomersReport, meta: { title: 'Inactive Customers', titleKey: 'Page_Inactive_Customers', permission: 'inactive_customers_report' } },
            { path: 'reports/draft-invoices', name: 'draft-invoices-report', component: DraftInvoicesReport, meta: { title: 'Draft Invoices', titleKey: 'Page_Draft_Invoices', permission: 'draft_invoices_report' } },
            { path: 'reports/top-suppliers', name: 'top-suppliers-report', component: TopSuppliersReport, meta: { title: 'Top Suppliers', titleKey: 'Page_Top_Suppliers', permission: 'Top_Suppliers_Report' } },
            { path: 'reports/warranty', name: 'warranty-report', component: WarrantyReport, meta: { title: 'Warranty & Guarantee', titleKey: 'Page_Warranty_Guarantee', permission: 'report_warranty' } },
            { path: 'reports/stock-adjustment', name: 'stock-adjustment-report', component: StockAdjustmentReport, meta: { title: 'Stock Adjustment', titleKey: 'Stock Adjustment', permission: 'Stock_Adjustment_Report' } },
            { path: 'reports/serial-sold', name: 'serial-sold-report', component: SerialSoldReport, meta: { title: 'Sold Serial Numbers', titleKey: 'Sold_Serial_Numbers', permission: 'serial_numbers_report' } },
            { path: 'reports/serial-available', name: 'serial-available-report', component: SerialAvailableReport, meta: { title: 'Available Serial Numbers', titleKey: 'Available_Serials', permission: 'serial_numbers_report' } },
            { path: 'reports/serial-movement', name: 'serial-movement-report', component: SerialMovementReport, meta: { title: 'Serial Movement Log', titleKey: 'Serial_Movement_Log', permission: 'serial_numbers_report' } },
            { path: 'reports/serial-inventory', name: 'serial-inventory-report', component: SerialInventoryReport, meta: { title: 'Product Serial Inventory', titleKey: 'Product_Serial_Inventory', permission: 'serial_numbers_report' } },
            { path: 'reports/batch-register', name: 'batch-register-report', component: BatchRegisterReport, meta: { title: 'Batch Register', titleKey: 'Batch_Register_Report', permission: 'Batch_Register_Report' } },
            // Payments family
            { path: 'reports/payments-sale', name: 'payments-sale-report', component: PaymentsReport, meta: { title: 'Payments — Sales', titleKey: 'Page_Payments_Sales', permission: 'Reports_payments_Sales', kind: 'sale' } },
            { path: 'reports/payments-purchase', name: 'payments-purchase-report', component: PaymentsReport, meta: { title: 'Payments — Purchases', titleKey: 'Page_Payments_Purchases', permission: 'Reports_payments_Purchases', kind: 'purchase' } },
            { path: 'reports/payments-sales-returns', name: 'payments-sales-returns-report', component: PaymentsReport, meta: { title: 'Payments — Sales Returns', titleKey: 'Page_Payments_Sales_Returns', permission: 'Reports_payments_Sale_Returns', kind: 'returns_sale' } },
            { path: 'reports/payments-purchases-returns', name: 'payments-purchases-returns-report', component: PaymentsReport, meta: { title: 'Payments — Purchases Returns', titleKey: 'Page_Payments_Purchases_Returns', permission: 'Reports_payments_purchase_Return', kind: 'returns_purchase' } },
            { path: 'reports/customers', name: 'customers-report', component: CustomersReport, meta: { title: 'Customers Report', titleKey: 'CustomersReport', permission: 'Reports_customers' } },
            { path: 'reports/suppliers', name: 'suppliers-report', component: SuppliersReport, meta: { title: 'Suppliers Report', titleKey: 'SuppliersReport', permission: 'Reports_suppliers' } },
            { path: 'reports/cash-registers', name: 'cash-register-report', component: CashRegisterReport, meta: { title: 'Cash Register Report', titleKey: 'Cash_Register_Report', permission: 'cash_register_report' } },
            { path: 'reports/attendance', name: 'attendance-report', component: AttendanceReport, meta: { title: 'Attendance Summary', titleKey: 'Page_Attendance_Summary', permission: 'report_attendance_summary' } },
            { path: 'reports/service-jobs', name: 'service-jobs-report', component: ServiceJobsReport, meta: { title: 'Service Jobs Report', titleKey: 'Service_Jobs_Report', permission: 'service_jobs_report' } },
            { path: 'reports/checklist-completion', name: 'checklist-completion-report', component: ChecklistCompletionReport, meta: { title: 'Checklist Completion', titleKey: 'Page_Checklist_Completion', permission: 'checklist_completion_report' } },
            { path: 'reports/maintenance-history', name: 'maintenance-history-report', component: MaintenanceHistoryReport, meta: { title: 'Customer Maintenance History', titleKey: 'Customer_Maintenance_History', permission: 'customer_maintenance_history_report' } },
            { path: 'reports/users', name: 'users-report', component: UsersReport, meta: { title: 'Users Report', titleKey: 'Users_Report', permission: 'users_report' } },
            { path: 'reports/login-activity', name: 'login-activity-report', component: LoginActivityReport, meta: { title: 'Login Activity', titleKey: 'Page_Login_Activity', permission: 'report_device_management' } },
            { path: 'reports/error-logs', name: 'error-logs-report', component: ErrorLogsReport, meta: { title: 'Error Logs', titleKey: 'Error_Logs', permission: 'report_error_logs' } },
            { path: 'reports/top-selling-products', name: 'top-selling-products-report', component: TopSellingProductsReport, meta: { title: 'Top Selling Products', titleKey: 'Top_Selling_Products', permission: 'Top_products' } },
            { path: 'reports/top-customers', name: 'top-customers-report', component: TopCustomersReport, meta: { title: 'Top Customers', titleKey: 'Page_Top_Customers', permission: 'Top_customers' } },
            { path: 'reports/expenses', name: 'expenses-report', component: ExpensesReport, meta: { title: 'Expenses Report', titleKey: 'Page_Expenses_Report', permission: 'expenses_report' } },
            { path: 'reports/deposits', name: 'deposits-report', component: DepositsReport, meta: { title: 'Deposits Report', titleKey: 'Deposits_Report', permission: 'deposits_report' } },
            { path: 'reports/sales', name: 'sales-report', component: SalesReport, meta: { title: 'Sales Report', titleKey: 'SalesReport', permission: 'Reports_sales' } },
            { path: 'reports/purchases', name: 'purchases-report', component: PurchasesReport, meta: { title: 'Purchases Report', titleKey: 'PurchasesReport', permission: 'Reports_purchase' } },
            { path: 'reports/landed-costs', name: 'landed-cost-report', component: LandedCostReport, meta: { title: 'Landed cost report', titleKey: 'Landed_Cost_Report', permission: 'Reports_purchase' } },
            { path: 'reports/quantity-alerts', name: 'quantity-alerts-report', component: QuantityAlertsReport, meta: { title: 'Quantity Alerts', titleKey: 'Page_Quantity_Alerts', permission: 'Reports_quantity_alerts' } },
            { path: 'reports/stock', name: 'stock-report', component: StockReport, meta: { title: 'Stock Report', titleKey: 'stock_report', permission: 'stock_report' } },
            { path: 'reports/product', name: 'product-report', component: ProductReport, meta: { title: 'Product Report', titleKey: 'Page_Product_Report', permission: 'product_report' } },
            { path: 'reports/customers/:id(\\d+)', name: 'customer-detail-report', component: CustomerDetailReport, meta: { title: 'Customer Report', titleKey: 'Page_Customer_Report', permission: 'Reports_customers' } },
            { path: 'reports/suppliers/:id(\\d+)', name: 'supplier-detail-report', component: SupplierDetailReport, meta: { title: 'Supplier Report', titleKey: 'SupplierReport', permission: 'Reports_suppliers' } },
            { path: 'reports/product/:id(\\d+)', name: 'product-detail-report', component: ProductDetailReport, meta: { title: 'Product Report', titleKey: 'Page_Product_Report', permission: 'product_report' } },
            { path: 'reports/stock/:id(\\d+)', name: 'stock-detail-report', component: StockDetailReport, meta: { title: 'Stock Report', titleKey: 'stock_report', permission: 'stock_report' } },
            { path: 'reports/users/:id(\\d+)', name: 'user-detail-report', component: UserDetailReport, meta: { title: 'User Report', titleKey: 'User_report', permission: 'users_report' } },
            { path: 'reports/batch-history/:id(\\d+)', name: 'batch-history-report', component: BatchHistoryReport, meta: { title: 'Batch History', titleKey: 'Batch_History', permission: 'Batch_Register_Report' } },
            { path: 'reports/product-sales', name: 'product-sales-report', component: ProductSalesReport, meta: { title: 'Product Sales Report', titleKey: 'Page_Product_Sales_Report', permission: 'product_sales_report' } },
            { path: 'reports/products-sold-summary', name: 'products-sold-summary-report', component: ProductsSoldSummaryReport, meta: { title: 'Products Sold Summary', titleKey: 'products_sold_summary', permission: 'products_sold_summary' } },
            { path: 'reports/product-purchases', name: 'product-purchases-report', component: ProductPurchasesReport, meta: { title: 'Product Purchases Report', titleKey: 'Page_Product_Purchases_Report', permission: 'product_purchases_report' } },
            { path: 'reports/seller', name: 'seller-report', component: SellerReport, meta: { title: 'Seller Report', titleKey: 'Seller_report', permission: 'seller_report' } },
            { path: 'reports/transactions', name: 'transactions-report', component: TransactionsReport, meta: { title: 'Transactions', titleKey: 'Transactions', permission: 'report_transactions' } },
            { path: 'reports/internal-location', name: 'internal-location-report', component: InternalLocationReport, meta: { title: 'Internal Location', titleKey: 'Page_Internal_Location', permission: 'internal_location_report' } },
            { path: 'reports/sales-by-category', name: 'sales-by-category-report', component: SalesByGroupReport, meta: { title: 'Sales by Category', titleKey: 'Sales_by_Category', permission: 'report_sales_by_category', kind: 'category' } },
            { path: 'reports/sales-by-brand', name: 'sales-by-brand-report', component: SalesByGroupReport, meta: { title: 'Sales by Brand', titleKey: 'Sales_by_Brand', permission: 'report_sales_by_brand', kind: 'brand' } },
            { path: 'reports/profit-and-loss', name: 'profit-and-loss-report', component: ProfitAndLossReport, meta: { title: 'Profit and Loss', titleKey: 'ProfitandLoss', permission: 'Reports_profit' } },
            // Profit analysis — one component, dimension picked by route meta.
            { path: 'reports/profit/product', name: 'profit-by-product', component: ProfitReport, meta: { title: 'Profit by Product', titleKey: 'Profit by Product', permission: 'Reports_profit', dimension: 'product' } },
            { path: 'reports/profit/category', name: 'profit-by-category', component: ProfitReport, meta: { title: 'Profit by Category', titleKey: 'Profit by Category', permission: 'Reports_profit', dimension: 'category' } },
            { path: 'reports/profit/unit', name: 'profit-by-unit', component: ProfitReport, meta: { title: 'Profit by Unit', titleKey: 'Profit by Unit', permission: 'Reports_profit', dimension: 'unit' } },
            { path: 'reports/profit/customer', name: 'profit-by-customer', component: ProfitReport, meta: { title: 'Profit by Customer', titleKey: 'Profit by Customer', permission: 'Reports_profit', dimension: 'customer' } },
            { path: 'reports/profit/date', name: 'profit-by-date', component: ProfitReport, meta: { title: 'Profit by Date', titleKey: 'Profit by Date', permission: 'Reports_profit', dimension: 'date' } },
            { path: 'reports/profit/warehouse', name: 'profit-by-warehouse', component: ProfitReport, meta: { title: 'Profit by Warehouse', titleKey: 'Profit by Warehouse', permission: 'Reports_profit', dimension: 'warehouse' } },
            { path: 'reports/analytics', name: 'analytics-report', component: AnalyticsReport, meta: { title: 'Analytics Report', titleKey: 'Analytics_Report', permission: 'analytics_report' } },
            { path: 'reports/cash-flow', name: 'cash-flow-report', component: CashFlowReport, meta: { title: 'Cash Flow', titleKey: 'cash_flow', permission: 'cash_flow_report' } },
            { path: 'reports/stock-transfer', name: 'stock-transfer-report', component: StockTransferReport, meta: { title: 'Stock Transfer', titleKey: 'StockTransfer', permission: 'Stock_Transfer_Report' } },
            { path: 'reports/return-ratio', name: 'return-ratio-report', component: ReturnRatioReport, meta: { title: 'Return Ratio', titleKey: 'Page_Return_Ratio', permission: 'return_ratio_report' } },
            { path: 'reports/stock-aging', name: 'stock-aging-report', component: StockAgingReport, meta: { title: 'Stock Aging', titleKey: 'Page_Stock_Aging', permission: 'Stock_Aging_Report' } },
            { path: 'reports/inventory-valuation-summary', name: 'inventory-valuation-summary-report', component: InventoryValuationSummaryReport, meta: { title: 'Inventory Valuation', titleKey: 'Inventory_Valuation', permission: 'inventory_valuation' } },
            { path: 'reports/stock-inventory-valuation', name: 'stock-inventory-valuation-report', component: StockInventoryValuationReport, meta: { title: 'Stock Inventory Valuation', titleKey: 'Stock_Inventory_Valuation', permission: 'Stock_Inventory_Valuation' } },
            { path: 'reports/warehouse', name: 'warehouse-report', component: WarehouseReport, meta: { title: 'Warehouse Report', titleKey: 'Page_Warehouse_Report', permission: 'Warehouse_report' } },
            // People
            { path: 'customers', name: 'customers', component: Customers, meta: { title: 'Customers', titleKey: 'Customers', permission: 'Customers_view' } },
            { path: 'customers/import', name: 'customers-import', component: ImportCustomers, meta: { title: 'Import Customers', titleKey: 'Import_Customers', permission: 'customers_import' } },
            { path: 'customers/create', name: 'customer-create', component: CustomerForm, meta: { title: 'Add Customer', titleKey: 'Page_Add_Customer', permission: 'Customers_add' } },
            // Edit permission isn't gated client-side in legacy; list access + server checks.
            { path: 'customers/:id/edit', name: 'customer-edit', component: CustomerForm, meta: { title: 'Edit Customer', titleKey: 'EditCustomer', permission: 'Customers_view' } },
            { path: 'customers/:id/details', name: 'customer-details', component: CustomerDetails, meta: { title: 'Customer Details', titleKey: 'CustomerDetails', permission: 'Customers_view' } },
            { path: 'customers/:id/ledger', name: 'customer-ledger', component: CustomerLedger, meta: { title: 'Customer Ledger', titleKey: 'Customer_Ledger', permission: 'Customers_view' } },
            { path: 'suppliers', name: 'suppliers', component: Suppliers, meta: { title: 'Suppliers', titleKey: 'Suppliers', permission: 'Suppliers_view' } },
            { path: 'suppliers/import', name: 'suppliers-import', component: ImportSuppliers, meta: { title: 'Import Suppliers', titleKey: 'Import_Suppliers', permission: 'Suppliers_import' } },
            { path: 'suppliers/create', name: 'supplier-create', component: SupplierForm, meta: { title: 'Add Supplier', titleKey: 'Page_Add_Supplier', permission: 'Suppliers_add' } },
            { path: 'suppliers/:id/edit', name: 'supplier-edit', component: SupplierForm, meta: { title: 'Edit Supplier', titleKey: 'Page_Edit_Supplier', permission: 'Suppliers_view' } },
            { path: 'suppliers/:id/details', name: 'supplier-details', component: SupplierDetails, meta: { title: 'Supplier Details', titleKey: 'SupplierDetails', permission: 'Suppliers_view' } },
            { path: 'users', name: 'users', component: Users, meta: { title: 'Users', titleKey: 'Users', permission: 'users_view' } },
            { path: 'users/create', name: 'user-create', component: UserForm, meta: { title: 'Add User', titleKey: 'Page_Add_User', permission: 'users_add' } },
            { path: 'users/:id/edit', name: 'user-edit', component: UserForm, meta: { title: 'Edit User', titleKey: 'Page_Edit_User', permission: 'users_view' } },
            // HRM
            { path: 'hrm/companies', name: 'hrm-companies', component: Companies, meta: { title: 'Companies', titleKey: 'Page_Companies', permission: 'company' } },
            { path: 'hrm/departments', name: 'hrm-departments', component: Departments, meta: { title: 'Departments', titleKey: 'Departments', permission: 'department' } },
            { path: 'hrm/designations', name: 'hrm-designations', component: Designations, meta: { title: 'Designations', titleKey: 'Designations', permission: 'designation' } },
            { path: 'hrm/holidays', name: 'hrm-holidays', component: Holidays, meta: { title: 'Holidays', titleKey: 'Holidays', permission: 'holiday' } },
            { path: 'hrm/office-shifts', name: 'hrm-office-shifts', component: OfficeShifts, meta: { title: 'Office Shifts', titleKey: 'Page_Office_Shifts', permission: 'office_shift' } },
            // Assets
            // Literal segments first - an `assets/:id` route would otherwise
            // swallow dashboard, reports and the rest as asset ids.
            // Manufacturing MRP. Literal segments before the :id routes.
            { path: 'mrp', name: 'mrp', component: MrpDashboard, meta: { title: 'Manufacturing', titleKey: 'Manufacturing', permission: 'mrp_production' } },
            { path: 'mrp/production-orders', name: 'mrp-orders', component: MrpProductionOrders, meta: { title: 'Production Orders', titleKey: 'Production Orders', permission: 'mrp_production' } },
            { path: 'mrp/production-orders/create', name: 'mrp-order-create', component: MrpProductionOrderForm, meta: { title: 'New Production Order', titleKey: 'Page_New_Production_Order', permission: 'mrp_production' } },
            { path: 'mrp/production-orders/:id/edit', name: 'mrp-order-edit', component: MrpProductionOrderForm, meta: { title: 'Edit Production Order', titleKey: 'Page_Edit_Production_Order', permission: 'mrp_production' } },
            { path: 'mrp/production-orders/:id', name: 'mrp-order-details', component: MrpProductionOrderDetails, meta: { title: 'Production Order', titleKey: 'Page_Production_Order', permission: 'mrp_production' } },
            { path: 'mrp/work-orders', name: 'mrp-work-orders', component: MrpWorkOrders, meta: { title: 'Shop Floor', titleKey: 'Shop Floor', permission: 'mrp_production' } },
            { path: 'mrp/boms', name: 'mrp-boms', component: MrpBoms, meta: { title: 'Bills of Materials', titleKey: 'Bills of Materials', permission: 'mrp_boms' } },
            { path: 'mrp/boms/create', name: 'mrp-bom-create', component: MrpBomForm, meta: { title: 'New BOM', titleKey: 'Mfg_New_Bom', permission: 'mrp_boms' } },
            { path: 'mrp/boms/:id/edit', name: 'mrp-bom-edit', component: MrpBomForm, meta: { title: 'Edit BOM', titleKey: 'Page_Edit_BOM', permission: 'mrp_boms' } },
            { path: 'mrp/work-centers', name: 'mrp-work-centers', component: MrpWorkCenters, meta: { title: 'Work Centres', titleKey: 'Work Centres', permission: 'mrp_boms' } },
            { path: 'mrp/quality', name: 'mrp-quality', component: MrpQualityChecks, meta: { title: 'Quality Control', titleKey: 'Quality Control', permission: 'mrp_quality' } },
            { path: 'mrp/planning', name: 'mrp-planning', component: MrpPlanning, meta: { title: 'MRP Planning', titleKey: 'Mfg_Mrp_Planning', permission: 'mrp_planning' } },
            { path: 'mrp/reports', name: 'mrp-reports', component: MrpReports, meta: { title: 'Manufacturing Reports', titleKey: 'Manufacturing_Reports', permission: 'mrp_reports' } },

            // Shopify. Literal segments before `stores/:id`.
            { path: 'shopify', name: 'shopify', component: ShopifyDashboard, meta: { title: 'Shopify', titleKey: 'Shopify', permission: 'shopify_stores' } },
            { path: 'shopify/stores', name: 'shopify-stores', component: ShopifyStores, meta: { title: 'Shopify Stores', titleKey: 'Shopify_Stores_Title', permission: 'shopify_stores' } },
            { path: 'shopify/sync', name: 'shopify-sync', component: ShopifySyncCenter, meta: { title: 'Sync Centre', titleKey: 'Sync Centre', permission: 'shopify_sync' } },
            { path: 'shopify/mappings', name: 'shopify-mappings', component: ShopifyMappings, meta: { title: 'Shopify Mappings', titleKey: 'Page_Shopify_Mappings', permission: 'shopify_sync' } },
            { path: 'shopify/logs', name: 'shopify-logs', component: ShopifyLogs, meta: { title: 'Shopify Logs', titleKey: 'Shopify_Logs_Title', permission: 'shopify_logs' } },
            { path: 'shopify/guide', name: 'shopify-guide', component: ShopifyGuidePage, meta: { title: 'Shopify Guide', titleKey: 'Page_Shopify_Guide', permission: 'shopify_stores' } },
            { path: 'shopify/stores/:id', name: 'shopify-store-details', component: ShopifyStoreDetails, meta: { title: 'Shopify Store', titleKey: 'Page_Shopify_Store', permission: 'shopify_stores' } },
            { path: 'assets/dashboard', name: 'assets-dashboard', component: AssetsDashboard, meta: { title: 'Asset Dashboard', titleKey: 'Asset_Dashboard', permission: 'assets' } },
            { path: 'assets/assignments', name: 'asset-assignments', component: AssetAssignments, meta: { title: 'Assignments', titleKey: 'Assignments', permission: 'asset_assignments' } },
            { path: 'assets/maintenance', name: 'asset-maintenance', component: AssetMaintenance, meta: { title: 'Asset Maintenance', titleKey: 'Page_Asset_Maintenance', permission: 'asset_maintenance' } },
            { path: 'assets/transfers', name: 'asset-transfers', component: AssetTransfers, meta: { title: 'Asset Transfers', titleKey: 'Page_Asset_Transfers', permission: 'asset_transfers' } },
            { path: 'assets/depreciation', name: 'asset-depreciation', component: AssetDepreciation, meta: { title: 'Depreciation', titleKey: 'Depreciation', permission: 'asset_reports' } },
            { path: 'assets/reports', name: 'asset-reports', component: AssetReports, meta: { title: 'Asset Reports', titleKey: 'Asset Reports', permission: 'asset_reports' } },
            { path: 'assets', name: 'assets', component: Assets, meta: { title: 'Assets', titleKey: 'Assets', permission: 'assets' } },
            { path: 'assets/create', name: 'asset-create', component: AssetForm, meta: { title: 'Add Asset', titleKey: 'Add_Asset', permission: 'assets' } },
            { path: 'assets/:id/edit', name: 'asset-edit', component: AssetForm, meta: { title: 'Edit Asset', titleKey: 'Edit_Asset', permission: 'assets' } },
            { path: 'assets/categories', name: 'asset-categories', component: AssetCategories, meta: { title: 'Asset Categories', titleKey: 'Asset_Categories', permission: 'assets' } },
            { path: 'assets/due', name: 'assets-due', component: DueAssets, meta: { title: 'Due Assets', titleKey: 'Page_Due_Assets', permission: 'assets' } },
            { path: 'assets/:id', name: 'asset-details', component: AssetDetails, meta: { title: 'Asset', titleKey: 'Asset', permission: 'assets' } },
            { path: 'subscriptions', name: 'subscriptions', component: Subscriptions, meta: { title: 'Subscriptions', titleKey: 'Subscriptions', permission: 'subscription_product' } },
            { path: 'subscriptions/create', name: 'subscription-create', component: SubscriptionForm, meta: { title: 'Create Subscription', titleKey: 'Page_Create_Subscription', permission: 'subscription_product' } },
            { path: 'subscriptions/:id(\\d+)', name: 'subscription-details', component: SubscriptionDetails, meta: { title: 'Subscription Details', titleKey: 'Page_Subscription_Details', permission: 'subscription_product' } },
            { path: 'bookings/calendar', name: 'booking-calendar', component: BookingCalendar, meta: { title: 'Booking Calendar', titleKey: 'Page_Booking_Calendar', permission: 'bookings' } },
            { path: 'service/jobs', name: 'service-jobs', component: ServiceJobsList, meta: { title: 'Service Jobs', titleKey: 'Service_Jobs', permission: 'service_jobs' } },
            { path: 'service/jobs/create', name: 'service-job-create', component: ServiceJobForm, meta: { title: 'Create Service Job', titleKey: 'Create_Service_Job', permission: 'service_jobs' } },
            { path: 'service/jobs/edit/:id(\\d+)', name: 'service-job-edit', component: ServiceJobForm, meta: { title: 'Edit Service Job', titleKey: 'Edit_Service_Job', permission: 'service_jobs' } },
            { path: 'service/jobs/details/:id(\\d+)', name: 'service-job-details', component: ServiceJobDetails, meta: { title: 'Service Job Details', titleKey: 'Service_Job_Details', permission: 'service_jobs' } },
            { path: 'service/technicians', name: 'service-technicians', component: ServiceTechnicians, meta: { title: 'Service Technicians', titleKey: 'Service_Technicians', permission: 'service_jobs' } },
            { path: 'service/checklist-categories', name: 'service-checklist-categories', component: ServiceChecklistCategories, meta: { title: 'Checklist Categories', titleKey: 'Checklist_Categories', permission: 'service_jobs' } },
            { path: 'service/checklists', name: 'service-checklists', component: ServiceChecklists, meta: { title: 'Checklist Items', titleKey: 'Checklist_Items', permission: 'service_jobs' } },
            { path: 'service/history', name: 'service-history', component: CustomerMaintenanceHistory, meta: { title: 'Maintenance History', titleKey: 'Maintenance_History', permission: 'service_jobs' } },
            { path: 'permissions', name: 'permissions', component: GroupPermissions, meta: { title: 'Group Permissions', titleKey: 'GroupPermissions', permission: 'permissions_view' } },
            { path: 'permissions/create', name: 'permission-create', component: PermissionForm, meta: { title: 'Create Permission', titleKey: 'Create_Permission', permission: 'permissions_add' } },
            { path: 'permissions/:id(\\d+)/edit', name: 'permission-edit', component: PermissionForm, meta: { title: 'Edit Permission', titleKey: 'Edit_Permission', permission: 'permissions_edit' } },
            { path: 'store/settings', name: 'store-settings', component: StoreSettings, meta: { title: 'Store Settings', titleKey: 'Page_Store_Settings', permission: 'Store_settings_view' } },
            { path: 'store/payment-gateway', name: 'store-payment-gateway', component: StorePaymentGateway, meta: { title: 'Payment Gateway', titleKey: 'Payment_Gateway', permission: 'payment_gateway' } },
            { path: 'store/orders', name: 'store-orders', component: StoreOrders, meta: { title: 'Online Orders', titleKey: 'Online_Orders', permission: 'Orders_view' } },
            { path: 'store/orders/:id(\\d+)', name: 'store-order-show', component: StoreOrderShow, meta: { title: 'Order', titleKey: 'Order', permission: 'Orders_view' } },
            { path: 'store/collections', name: 'store-collections', component: StoreCollections, meta: { title: 'Collections', titleKey: 'Collections', permission: 'Collections_view' } },
            { path: 'store/collections/create', name: 'store-collection-create', component: StoreCollectionForm, meta: { title: 'New Collection', titleKey: 'New_Collection', permission: 'Collections_view' } },
            { path: 'store/collections/edit/:id(\\d+)', name: 'store-collection-edit', component: StoreCollectionForm, meta: { title: 'Edit Collection', titleKey: 'Edit_Collection', permission: 'Collections_view' } },
            { path: 'store/messages', name: 'store-messages', component: StoreMessages, meta: { title: 'Messages', titleKey: 'Messages', permission: 'Messages_view' } },
            { path: 'store/invite-codes', name: 'store-invite-codes', component: StoreInviteCodes, meta: { title: 'Invite Codes', titleKey: 'Invite_Codes', permission: 'Store_settings_view' } },
            { path: 'store/flash-sales', name: 'store-flash-sales', component: StoreFlashSales, meta: { title: 'Flash Sales', titleKey: 'Flash_Sales', permission: 'Store_settings_view' } },
            { path: 'store/subscribers', name: 'store-subscribers', component: StoreSubscribers, meta: { title: 'Subscribers', titleKey: 'Subscribers', permission: 'Subscribers_view' } },
            { path: 'store/quote-requests', name: 'store-quote-requests', component: StoreQuoteRequests, meta: { title: 'Quote Requests', titleKey: 'Quote_Requests', permission: 'Orders_view' } },
            { path: 'store/reviews', name: 'store-reviews', component: StoreReviews, meta: { title: 'Product Reviews', titleKey: 'Product_Reviews', permission: 'Store_settings_view' } },
            { path: 'store/questions', name: 'store-questions', component: StoreQuestions, meta: { title: 'Customer Questions', titleKey: 'Customer_Questions', permission: 'Store_settings_view' } },
            { path: 'store/returns', name: 'store-returns', component: StoreReturns, meta: { title: 'Returns Requests', titleKey: 'Page_Returns_Requests', permission: 'Orders_view' } },
            { path: 'store/menus', name: 'store-menus', component: StoreMenus, meta: { title: 'Menus', titleKey: 'Menus', permission: 'Store_settings_view' } },
            { path: 'store/languages', name: 'store-languages', component: StoreLanguages, meta: { title: 'Store Languages', titleKey: 'Store_Languages', permission: 'Store_settings_view' } },
            { path: 'store/translations/:locale', name: 'store-translations', component: StoreTranslations, meta: { title: 'Store Translations', titleKey: 'Store_Translations', permission: 'Store_settings_view' } },
            { path: 'store/coupons', name: 'store-coupons', component: StoreCoupons, meta: { title: 'Coupons', titleKey: 'Coupons', permission: 'Store_settings_view' } },
            { path: 'store/tax-rates', name: 'store-tax-rates', component: StoreTaxRates, meta: { title: 'Tax Rates', titleKey: 'Tax_Rates', permission: 'Store_settings_view' } },
            { path: 'store/pending-customers', name: 'store-pending-customers', component: StorePendingCustomers, meta: { title: 'Pending Customers', titleKey: 'Pending_Customers', permission: 'Store_settings_view' } },
            { path: 'store/pages', name: 'store-pages', component: StorePages, meta: { title: 'Pages', titleKey: 'Pages', permission: 'Store_settings_view' } },
            { path: 'store/popups', name: 'store-popups', component: StorePopups, meta: { title: 'Popup Messages', titleKey: 'Popup_Messages', permission: 'Store_settings_view' } },
            { path: 'store/shipping-methods', name: 'store-shipping-methods', component: StoreShippingMethods, meta: { title: 'Shipping Methods', titleKey: 'Shipping_Methods', permission: 'Store_settings_view' } },
            { path: 'store/shipping-zones', name: 'store-shipping-zones', component: StoreShippingZones, meta: { title: 'Shipping Zones', titleKey: 'Shipping_Zones', permission: 'Store_settings_view' } },
            { path: 'store/banners', name: 'store-banners', component: StoreBanners, meta: { title: 'Banners', titleKey: 'Banners', permission: 'Banners_view' } },
            { path: 'store/banners/edit/:id', name: 'store-banner-edit', component: StoreBannerForm, meta: { title: 'Banner', titleKey: 'Banner', permission: 'Banners_view' } },
            { path: 'realestate/properties', name: 'realestate-properties', component: Properties, meta: { title: 'Properties', titleKey: 'Properties', permission: 'realestate_properties' } },
            { path: 'realestate/properties/create', name: 'realestate-property-create', component: PropertyForm, meta: { title: 'Add Property', titleKey: 'Add_Property', permission: 'realestate_properties' } },
            { path: 'realestate/properties/edit/:id(\\d+)', name: 'realestate-property-edit', component: PropertyForm, meta: { title: 'Edit Property', titleKey: 'Edit_Property', permission: 'realestate_properties' } },
            { path: 'realestate/categories', name: 'realestate-categories', component: PropertyCategories, meta: { title: 'Property Categories', titleKey: 'Property_Categories', permission: 'realestate_categories' } },
            { path: 'realestate/inquiries', name: 'realestate-inquiries', component: PropertyInquiries, meta: { title: 'Property Inquiries', titleKey: 'Property_Inquiries', permission: 'realestate_inquiries' } },
            { path: 'reports/ai-reports', name: 'ai-reports', component: AiReports, meta: { title: 'AI Reports', titleKey: 'AI_Reports', permission: 'AI_Reports' } },
            { path: 'reports/sales-3d-dashboard', name: 'sales-3d-dashboard', component: Sales3DDashboard, meta: { title: '3D Sales Dashboard', titleKey: 'sales_3d_dashboard', permission: 'sales_3d_dashboard' } },
            { path: 'accounting-v2/dashboard', name: 'accounting-v2-dashboard', component: AccountingDashboard, meta: { title: 'Accounting Dashboard', titleKey: 'Page_Accounting_Dashboard', permission: 'accounting_dashboard' } },
            { path: 'accounting-v2/chart-of-accounts', name: 'accounting-v2-coa', component: ChartOfAccounts, meta: { title: 'Chart of Accounts', titleKey: 'Chart_of_Accounts_Link', permission: 'chart_of_accounts' } },
            { path: 'accounting-v2/journal-entries', name: 'accounting-v2-journal-entries', component: JournalEntries, meta: { title: 'Journal Entries', titleKey: 'Journal_Entries_Link', permission: 'journal_entries' } },
            { path: 'accounting-v2/reports/trial-balance', name: 'accounting-v2-trial-balance', component: TrialBalance, meta: { title: 'Trial Balance', titleKey: 'Trial_Balance_Link', permission: 'journal_entries' } },
            { path: 'accounting-v2/reports/profit-and-loss', name: 'accounting-v2-profit-loss', component: ProfitAndLossV2, meta: { title: 'Profit and Loss', titleKey: 'ProfitandLoss', permission: 'journal_entries' } },
            { path: 'accounting-v2/reports/balance-sheet', name: 'accounting-v2-balance-sheet', component: BalanceSheet, meta: { title: 'Balance Sheet', titleKey: 'Balance_Sheet_Link', permission: 'journal_entries' } },
            { path: 'accounting-v2/reports/tax-report', name: 'accounting-v2-tax-report', component: TaxReportV2, meta: { title: 'Tax Summary Report', titleKey: 'Tax_Summary_Report', permission: 'accounting_tax_report' } },
            { path: 'meeting/dashboard', name: 'meeting-dashboard', component: MeetingDashboard, meta: { title: 'Meeting Dashboard', titleKey: 'Page_Meeting_Dashboard', permission: 'meeting' } },
            { path: 'meeting/details/:id(\\d+)', name: 'meeting-details', component: MeetingDetails, meta: { title: 'Meeting Details', titleKey: 'Meeting_Details', permission: 'meeting' } },
            { path: 'meeting/calendar', name: 'meeting-calendar', component: MeetingCalendar, meta: { title: 'Meeting Calendar', titleKey: 'Page_Meeting_Calendar', permission: 'meeting' } },
            { path: 'meeting/reports', name: 'meeting-reports', component: MeetingReports, meta: { title: 'Meeting Reports', titleKey: 'Page_Meeting_Reports', permission: 'meeting_report' } },
            { path: 'ewallet/dashboard', name: 'ewallet-dashboard', component: WalletDashboard, meta: { title: 'E-Wallet Dashboard', titleKey: 'E_Wallet_Dashboard', permission: 'ewallet' } },
            { path: 'ewallet/items', name: 'ewallet-items', component: WalletItems, meta: { title: 'Wallet Items', titleKey: 'Wallet_Items', permission: 'ewallet' } },
            { path: 'ewallet/settings', name: 'ewallet-settings', component: WalletSettings, meta: { title: 'E-Wallet Settings', titleKey: 'E_Wallet_Settings', permission: 'ewallet' } },
            { path: 'recruit/dashboard', name: 'recruit-dashboard', component: RecruitDashboard, meta: { title: 'Recruit Dashboard', titleKey: 'Page_Recruit_Dashboard', permission: 'recruit_job' } },
            { path: 'recruit/jobs', name: 'recruit-jobs', component: RecruitJobs, meta: { title: 'Jobs', titleKey: 'Jobs', permission: 'recruit_job' } },
            { path: 'recruit/categories', name: 'recruit-categories', component: RecruitCategories, meta: { title: 'Job Categories', titleKey: 'Job_Categories', permission: 'recruit_category' } },
            { path: 'recruit/candidates', name: 'recruit-candidates', component: RecruitCandidates, meta: { title: 'Candidates', titleKey: 'Candidates', permission: 'recruit_candidate' } },
            { path: 'recruit/applications', name: 'recruit-applications', component: RecruitApplications, meta: { title: 'Applications', titleKey: 'Applications', permission: 'recruit_application' } },
            { path: 'recruit/interviews', name: 'recruit-interviews', component: RecruitInterviews, meta: { title: 'Interviews', titleKey: 'Interviews', permission: 'recruit_interview' } },
            { path: 'recruit/reports', name: 'recruit-reports', component: RecruitReports, meta: { title: 'Recruit Reports', titleKey: 'Page_Recruit_Reports', permission: 'recruit_job' } },
            { path: 'commissions/programs', name: 'commission-programs', component: CommissionPrograms, meta: { title: 'Commission Programs', titleKey: 'Commission_Programs', permission: 'commissions_view' } },
            { path: 'commissions/agents', name: 'commission-agents', component: SalesAgents, meta: { title: 'Sales Agents', titleKey: 'Sales_Agents', permission: 'commissions_view' } },
            { path: 'commissions/rules', name: 'commission-rules', component: CommissionRules, meta: { title: 'Commission Rules', titleKey: 'Commission_Rules', permission: 'commissions_view' } },
            { path: 'commissions/receipts', name: 'commission-receipts', component: CommissionReceipts, meta: { title: 'Commission Receipts', titleKey: 'Commission_Receipts', permission: 'commissions_view' } },
            { path: 'commissions/report', name: 'commission-report', component: CommissionReport, meta: { title: 'Commission Report', titleKey: 'Commission_Report', permission: 'commissions_view' } },
            // Bookings
            { path: 'bookings', name: 'bookings', component: Bookings, meta: { title: 'Bookings', titleKey: 'Bookings', permission: 'bookings' } },
            { path: 'bookings/create', name: 'booking-create', component: BookingForm, meta: { title: 'Create Booking', titleKey: 'Create_Booking', permission: 'bookings' } },
            { path: 'bookings/:id(\\d+)/edit', name: 'booking-edit', component: BookingForm, meta: { title: 'Edit Booking', titleKey: 'Edit_Booking', permission: 'bookings' } },
            { path: 'kitchen-display', name: 'kitchen-display', component: KitchenDisplay, meta: { title: 'Kitchen Display', titleKey: 'KitchenDisplay', permission: 'kitchen_display_view' } },
            { path: 'kitchen/report', name: 'kitchen-report', component: KitchenReport, meta: { title: 'Kitchen Report', titleKey: 'KitchenReport', permission: 'kitchen_display_view' } },
            { path: 'bookings/trays', name: 'booking-trays', component: Trays, meta: { title: 'Trays', titleKey: 'Trays', permission: 'trays' } },
            { path: 'meetings', name: 'meetings', component: Meetings, meta: { title: 'Meetings', titleKey: 'Meetings', permission: 'meeting' } },
            // Knowledge base
            { path: 'profile', name: 'profile', component: Profile, meta: { title: 'Profile', titleKey: 'profil' } },
            { path: 'kb', name: 'kb', component: KnowledgeBase, meta: { title: 'Knowledge Base', titleKey: 'Knowledge_Base', permission: 'knowledge_base_view' } },
            { path: 'kb/groups', name: 'kb-groups', component: KnowledgeBaseGroups, meta: { title: 'Article Groups', titleKey: 'Article Groups', permission: 'knowledge_base_view' } },
            { path: 'client-portal/dashboard', name: 'client-portal-dashboard', component: PortalDashboard, meta: { title: 'Portal Dashboard', titleKey: 'Portal_Dashboard', permission: 'portal_accounts' } },
            { path: 'client-portal/accounts', name: 'client-portal-accounts', component: PortalAccounts, meta: { title: 'Portal Accounts', titleKey: 'Portal_Accounts', permission: 'portal_accounts' } },
            { path: 'client-portal/deletions', name: 'client-portal-deletions', component: PortalDeletionLog, meta: { title: 'Deletion Log', titleKey: 'Portal_Deletion_Log', permission: 'portal_accounts' } },
            { path: 'client-portal/settings', name: 'client-portal-settings', component: PortalSettings, meta: { title: 'Portal Settings', titleKey: 'Portal_Settings', permission: 'portal_settings' } },
            { path: 'client-portal/languages', name: 'client-portal-languages', component: PortalLanguages, meta: { title: 'Portal Languages', titleKey: 'Portal_Languages', permission: 'portal_settings' } },
            { path: 'client-portal/translations/:locale', name: 'client-portal-translations', component: PortalTranslations, meta: { title: 'Portal Translations', titleKey: 'Portal_Translations', permission: 'portal_settings' } },
            // Backend policy gates create/update/delete on knowledge_base_view too —
            // there is no knowledge_base_manage permission anywhere in the system.
            { path: 'kb/articles/create', name: 'kb-article-create', component: KnowledgeBaseArticleForm, meta: { title: 'New Article', titleKey: 'Page_New_Article', permission: 'knowledge_base_view' } },
            { path: 'kb/articles/:id/edit', name: 'kb-article-edit', component: KnowledgeBaseArticleForm, meta: { title: 'Edit Article', titleKey: 'Page_Edit_Article', permission: 'knowledge_base_view' } },
            { path: 'kb/articles/:id', name: 'kb-article', component: KnowledgeBaseArticle, meta: { title: 'Article', titleKey: 'Article', permission: 'knowledge_base_view' } },
            // Projects
            // Literal segments first — a `projects/:id` route would otherwise
            // read "dashboard" as a project id.
            { path: 'projects/dashboard', name: 'projects-dashboard', component: ProjectsDashboard, meta: { title: 'Projects Dashboard', titleKey: 'Proj_Dashboard_Title', permission: 'projects' } },
            { path: 'projects/milestones', name: 'project-milestones', component: ProjectMilestones, meta: { title: 'Milestones', titleKey: 'Milestones', permission: 'project_milestones' } },
            { path: 'projects/timesheets', name: 'project-timesheets', component: ProjectTimesheets, meta: { title: 'Timesheets', titleKey: 'Timesheets', permission: 'project_timesheets' } },
            { path: 'projects/reports', name: 'project-reports', component: ProjectReports, meta: { title: 'Project Reports', titleKey: 'Project Reports', permission: 'project_reports' } },
            { path: 'tasks/board', name: 'task-board', component: TaskBoard, meta: { title: 'Task Board', titleKey: 'Task Board', permission: 'tasks' } },
            { path: 'projects', name: 'projects', component: Projects, meta: { title: 'Projects', titleKey: 'Projects', permission: 'projects' } },
            { path: 'projects/create', name: 'project-create', component: ProjectForm, meta: { title: 'Add Project', titleKey: 'Page_Add_Project', permission: 'projects' } },
            { path: 'projects/:id/edit', name: 'project-edit', component: ProjectForm, meta: { title: 'Edit Project', titleKey: 'Edit_Project', permission: 'projects' } },
            { path: 'tasks', name: 'tasks', component: Tasks, meta: { title: 'Tasks', titleKey: 'Tasks', permission: 'tasks' } },
            { path: 'tasks/create', name: 'task-create', component: TaskForm, meta: { title: 'Add Task', titleKey: 'Page_Add_Task', permission: 'tasks' } },
            { path: 'tasks/:id/edit', name: 'task-edit', component: TaskForm, meta: { title: 'Edit Task', titleKey: 'Edit_Task', permission: 'tasks' } },
            { path: 'promotions', name: 'promotions', component: Promotions, meta: { title: 'Promotions', titleKey: 'Promotions', permission: 'promotion' } },
            { path: 'promotions/create', name: 'promotion-create', component: PromotionForm, meta: { title: 'Add Promotion', titleKey: 'Page_Add_Promotion', permission: 'promotion' } },
            { path: 'promotions/:id(\\d+)/edit', name: 'promotion-edit', component: PromotionForm, meta: { title: 'Edit Promotion', titleKey: 'Page_Edit_Promotion', permission: 'promotion' } },
            { path: 'promotions/usages', name: 'promotion-usages', component: PromotionUsages, meta: { title: 'Promotion Usage Report', titleKey: 'Promo_Usage_Report_Title', permission: 'promotion' } },
            { path: 'contracts', name: 'contracts', component: Contracts, meta: { title: 'Contracts', titleKey: 'Contracts', permission: 'contracts' } },
            { path: 'contracts/create', name: 'contract-create', component: ContractForm, meta: { title: 'Add Contract', titleKey: 'Page_Add_Contract', permission: 'contracts' } },
            { path: 'contracts/:id/edit', name: 'contract-edit', component: ContractForm, meta: { title: 'Edit Contract', titleKey: 'Edit_Contract', permission: 'contracts' } },
            { path: 'contracts/:id(\\d+)', name: 'contract-view', component: ContractView, meta: { title: 'View Contract', titleKey: 'Page_View_Contract', permission: 'contracts' } },
            // Folders, upload, preview and versions all live on one page — the
            // archive is a workspace, not a list + detail pair.
            { path: 'documents', name: 'documents', component: DocumentArchive, meta: { title: 'Document Archive', titleKey: 'Document Archive', permission: 'documents_view' } },
            // Vehicle & Fleet Management. `create` is declared before the
            // :id route so it is never read as a vehicle id.
            { path: 'fleet/dashboard', name: 'fleet-dashboard', component: FleetDashboard, meta: { title: 'Fleet Dashboard', titleKey: 'Fleet_Dashboard', permission: 'fleet_vehicles_view' } },
            { path: 'fleet/vehicles', name: 'fleet-vehicles', component: Vehicles, meta: { title: 'Vehicles', titleKey: 'Vehicles', permission: 'fleet_vehicles_view' } },
            { path: 'fleet/vehicles/create', name: 'fleet-vehicle-create', component: VehicleForm, meta: { title: 'Add Vehicle', titleKey: 'Page_Add_Vehicle', permission: 'fleet_vehicles_add' } },
            { path: 'fleet/vehicles/:id(\\d+)/edit', name: 'fleet-vehicle-edit', component: VehicleForm, meta: { title: 'Edit Vehicle', titleKey: 'Page_Edit_Vehicle', permission: 'fleet_vehicles_edit' } },
            { path: 'fleet/vehicles/:id(\\d+)', name: 'fleet-vehicle', component: VehicleDetails, meta: { title: 'Vehicle', titleKey: 'Vehicle', permission: 'fleet_vehicles_view' } },
            { path: 'fleet/maintenance', name: 'fleet-maintenance', component: FleetMaintenance, meta: { title: 'Fleet Maintenance', titleKey: 'Page_Fleet_Maintenance', permission: 'fleet_maintenance' } },
            { path: 'fleet/fuel-logs', name: 'fleet-fuel-logs', component: FleetFuelLogs, meta: { title: 'Fuel Logs', titleKey: 'Fuel Logs', permission: 'fleet_fuel' } },
            { path: 'fleet/assignments', name: 'fleet-assignments', component: FleetAssignments, meta: { title: 'Vehicle Assignments', titleKey: 'Page_Vehicle_Assignments', permission: 'fleet_assignments' } },
            { path: 'fleet/reports', name: 'fleet-reports', component: FleetReports, meta: { title: 'Fleet Reports', titleKey: 'Fleet Reports', permission: 'fleet_reports' } },
            // Rental Management. `create` is declared before the :id route so it
            // is never read as an item id.
            { path: 'rental/dashboard', name: 'rental-dashboard', component: RentalDashboard, meta: { title: 'Rental Dashboard', titleKey: 'Rental_Dashboard', permission: 'rnt_dashboard' } },
            { path: 'rental/items', name: 'rental-items', component: RentalItems, meta: { title: 'Rental Items', titleKey: 'Rental_Items', permission: 'rnt_items_view' } },
            { path: 'rental/items/create', name: 'rental-item-create', component: RentalItemForm, meta: { title: 'Add Rental Item', titleKey: 'Page_Add_Rental_Item', permission: 'rnt_items_add' } },
            { path: 'rental/items/:id(\\d+)/edit', name: 'rental-item-edit', component: RentalItemForm, meta: { title: 'Edit Rental Item', titleKey: 'Page_Edit_Rental_Item', permission: 'rnt_items_edit' } },
            { path: 'rental/items/:id(\\d+)', name: 'rental-item', component: RentalItemDetails, meta: { title: 'Rental Item', titleKey: 'Page_Rental_Item', permission: 'rnt_items_view' } },
            { path: 'rental/customers', name: 'rental-customers', component: RentalCustomers, meta: { title: 'Rental Customers', titleKey: 'Rental_Customers', permission: 'rnt_customers_view' } },
            { path: 'rental/customers/create', name: 'rental-customer-create', component: RentalCustomerForm, meta: { title: 'Add Rental Customer', titleKey: 'Page_Add_Rental_Customer', permission: 'rnt_customers_add' } },
            { path: 'rental/customers/:id(\\d+)/edit', name: 'rental-customer-edit', component: RentalCustomerForm, meta: { title: 'Edit Rental Customer', titleKey: 'Page_Edit_Rental_Customer', permission: 'rnt_customers_edit' } },
            { path: 'rental/customers/:id(\\d+)', name: 'rental-customer', component: RentalCustomerDetails, meta: { title: 'Rental Customer', titleKey: 'Page_Rental_Customer', permission: 'rnt_customers_view' } },
            { path: 'rental/contracts', name: 'rental-contracts', component: RentalContracts, meta: { title: 'Rental Contracts', titleKey: 'Rental_Contracts', permission: 'rnt_contracts_view' } },
            { path: 'rental/contracts/create', name: 'rental-contract-create', component: RentalContractForm, meta: { title: 'New Rental Contract', titleKey: 'Page_New_Rental_Contract', permission: 'rnt_contracts_add' } },
            { path: 'rental/contracts/:id(\\d+)/edit', name: 'rental-contract-edit', component: RentalContractForm, meta: { title: 'Edit Rental Contract', titleKey: 'Page_Edit_Rental_Contract', permission: 'rnt_contracts_edit' } },
            { path: 'rental/contracts/:id(\\d+)', name: 'rental-contract', component: RentalContractDetails, meta: { title: 'Rental Contract', titleKey: 'Page_Rental_Contract', permission: 'rnt_contracts_view' } },
            { path: 'rental/agreement-templates', name: 'rental-agreement-templates', component: RentalAgreementTemplates, meta: { title: 'Agreement Templates', titleKey: 'Rental_Agreement_Templates', permission: 'rnt_settings' } },
            { path: 'rental/availability', name: 'rental-availability', component: RentalAvailability, meta: { title: 'Rental Availability', titleKey: 'Page_Rental_Availability', permission: 'rnt_availability' } },
            { path: 'rental/calendar', name: 'rental-calendar', component: RentalCalendar, meta: { title: 'Rental Calendar', titleKey: 'Page_Rental_Calendar', permission: 'rnt_availability' } },
            { path: 'rental/reservations', name: 'rental-reservations', component: RentalReservations, meta: { title: 'Reservations', titleKey: 'Rental_Reservations', permission: 'rnt_reservations_view' } },
            { path: 'rental/reservations/create', name: 'rental-reservation-create', component: RentalReservationForm, meta: { title: 'New Reservation', titleKey: 'Page_New_Reservation', permission: 'rnt_reservations_add' } },
            { path: 'rental/reservations/:id(\\d+)/edit', name: 'rental-reservation-edit', component: RentalReservationForm, meta: { title: 'Edit Reservation', titleKey: 'Page_Edit_Reservation', permission: 'rnt_reservations_edit' } },
            { path: 'rental/reservations/:id(\\d+)', name: 'rental-reservation', component: RentalReservationDetails, meta: { title: 'Reservation', titleKey: 'Rental_Reservation', permission: 'rnt_reservations_view' } },
            { path: 'rental/invoices', name: 'rental-invoices', component: RentalInvoices, meta: { title: 'Rental Invoices', titleKey: 'Rental_Invoices', permission: 'rnt_billing_view' } },
            { path: 'rental/invoices/:id(\\d+)', name: 'rental-invoice', component: RentalInvoiceDetails, meta: { title: 'Rental Invoice', titleKey: 'Page_Rental_Invoice', permission: 'rnt_billing_view' } },
            { path: 'rental/payments', name: 'rental-payments', component: RentalPayments, meta: { title: 'Rental Payments', titleKey: 'Rental_Payments', permission: 'rnt_billing_view' } },
            { path: 'rental/contracts/:id(\\d+)/handover/:type(checkout|checkin)', name: 'rental-handover', component: RentalHandover, meta: { title: 'Rental Handover', titleKey: 'Page_Rental_Handover', permission: 'rnt_handover' } },
            { path: 'rental/maintenance', name: 'rental-maintenance', component: RentalMaintenance, meta: { title: 'Rental Maintenance', titleKey: 'Page_Rental_Maintenance', permission: 'rnt_maintenance' } },
            { path: 'rental/expenses', name: 'rental-expenses', component: RentalExpenses, meta: { title: 'Rental Expenses', titleKey: 'Rental_Expenses', permission: 'rnt_expenses' } },
            { path: 'rental/reports', name: 'rental-reports', component: RentalReports, meta: { title: 'Rental Reports', titleKey: 'Rental_Reports', permission: 'rnt_reports' } },
            { path: 'rental/customers/:id(\\d+)/statement', name: 'rental-statement', component: RentalStatement, meta: { title: 'Customer Statement', titleKey: 'Page_Customer_Statement', permission: 'rnt_reports' } },
            { path: 'rental/categories', name: 'rental-categories', component: RentalCategories, meta: { title: 'Rental Categories', titleKey: 'Rental_Categories', permission: 'rnt_categories' } },
            { path: 'rental/settings', name: 'rental-settings', component: RentalSettings, meta: { title: 'Rental Settings', titleKey: 'Rental_Settings', permission: 'rnt_settings' } },
            // CRM
            { path: 'crm/dashboard', name: 'crm-dashboard', component: CrmDashboard, meta: { title: 'CRM Dashboard', titleKey: 'Crm_Dashboard', permission: 'crm_dashboard' } },
            { path: 'crm/leads', name: 'crm-leads', component: CrmLeads, meta: { title: 'Leads', titleKey: 'Crm_Leads', permission: 'crm_leads_view' } },
            { path: 'crm/leads/create', name: 'crm-lead-create', component: CrmLeadForm, meta: { title: 'New Lead', titleKey: 'Page_New_Lead', permission: 'crm_leads_add' } },
            { path: 'crm/leads/import', name: 'crm-lead-import', component: CrmLeadImport, meta: { title: 'Import Leads', titleKey: 'Page_Import_Leads', permission: 'crm_import_export' } },
            { path: 'crm/leads/:id(\\d+)/edit', name: 'crm-lead-edit', component: CrmLeadForm, meta: { title: 'Edit Lead', titleKey: 'Page_Edit_Lead', permission: 'crm_leads_edit' } },
            { path: 'crm/leads/:id(\\d+)', name: 'crm-lead', component: CrmLeadDetails, meta: { title: 'Lead', titleKey: 'Lead', permission: 'crm_leads_view' } },
            { path: 'crm/settings', name: 'crm-settings', component: CrmSettings, meta: { title: 'CRM Settings', titleKey: 'Crm_Settings', permission: 'crm_settings' } },
            { path: 'crm/customers', name: 'crm-customers', component: CrmCustomers, meta: { title: 'CRM Customers', titleKey: 'Page_CRM_Customers', permission: 'crm_customers_view' } },
            { path: 'crm/customers/:id(\\d+)', name: 'crm-customer', component: CrmCustomer360, meta: { title: 'Customer 360', titleKey: 'Page_Customer_360', permission: 'crm_customers_view' } },
            { path: 'crm/reports', name: 'crm-reports', component: CrmReports, meta: { title: 'CRM Reports', titleKey: 'Page_CRM_Reports', permission: 'crm_reports' } },
            { path: 'crm/targets', name: 'crm-targets', component: CrmTargets, meta: { title: 'Sales Targets', titleKey: 'Page_Sales_Targets', permission: 'crm_targets' } },
            // Courier integration (module 'couriers').
            { path: 'couriers/accounts', name: 'courier-accounts', component: CourierAccounts, meta: { title: 'Courier Accounts', titleKey: 'Cr_Courier_Accounts', permission: 'courier_settings' } },
            { path: 'couriers/settings', name: 'courier-settings', component: CourierSettings, meta: { title: 'Courier Settings', titleKey: 'Cr_Courier_Settings', permission: 'courier_settings' } },
            { path: 'couriers/shipments', name: 'courier-shipments', component: CourierShipments, meta: { title: 'Courier Shipments', titleKey: 'Cr_Courier_Shipments', permission: 'courier_shipments' } },
            { path: 'couriers/reports', name: 'courier-reports', component: CourierReports, meta: { title: 'Courier Report', titleKey: 'Cr_Courier_Reports', permission: 'courier_reports' } },
            { path: 'couriers/logs', name: 'courier-logs', component: CourierLogs, meta: { title: 'Courier Logs', titleKey: 'Cr_Courier_Logs', permission: 'courier_settings' } },
            { path: 'couriers/guide', name: 'courier-guide', component: CourierGuide, meta: { title: 'Courier Guide', titleKey: 'Cr_Courier_Guide', permission: ['courier_settings', 'courier_shipments', 'courier_book'] } },
            { path: 'crm/web-forms', name: 'crm-web-forms', component: CrmWebForms, meta: { title: 'Web Forms', titleKey: 'Page_Web_Forms', permission: 'crm_web_forms' } },
            { path: 'crm/inbox', name: 'crm-inbox', component: CrmInbox, meta: { title: 'CRM Inbox', titleKey: 'Page_CRM_Inbox', permission: 'crm_messages' } },
            { path: 'crm/my-day', name: 'crm-my-day', component: CrmMyDay, meta: { title: 'My Day', titleKey: 'Crm_My_Day', permission: 'crm_activities_view' } },
            { path: 'crm/activities', name: 'crm-activities', component: CrmActivities, meta: { title: 'Activities', titleKey: 'Crm_Activities', permission: 'crm_activities_view' } },
            { path: 'crm/pipeline', name: 'crm-pipeline', component: CrmPipelineBoard, meta: { title: 'Pipeline', titleKey: 'Pipeline', permission: 'crm_deals_view' } },
            { path: 'crm/deals', name: 'crm-deals', component: CrmDeals, meta: { title: 'Deals', titleKey: 'Crm_Deals', permission: 'crm_deals_view' } },
            { path: 'crm/deals/create', name: 'crm-deal-create', component: CrmDealForm, meta: { title: 'New Deal', titleKey: 'Page_New_Deal', permission: 'crm_deals_add' } },
            { path: 'crm/deals/:id(\\d+)/edit', name: 'crm-deal-edit', component: CrmDealForm, meta: { title: 'Edit Deal', titleKey: 'Page_Edit_Deal', permission: 'crm_deals_edit' } },
            { path: 'crm/deals/:id(\\d+)', name: 'crm-deal', component: CrmDealDetails, meta: { title: 'Deal', titleKey: 'Crm_Deal', permission: 'crm_deals_view' } },
            // Hospital Management. `create` is declared before the :id routes
            // so it is never read as a record id.
            { path: 'hospital/dashboard', name: 'hospital-dashboard', component: HospitalDashboard, meta: { title: 'Hospital Dashboard', titleKey: 'Hosp_Dashboard_Title', permission: 'hms_dashboard' } },
            { path: 'hospital/patients', name: 'hospital-patients', component: HospitalPatients, meta: { title: 'Patients', titleKey: 'Patients', permission: 'hms_patients_view' } },
            { path: 'hospital/patients/create', name: 'hospital-patient-create', component: PatientForm, meta: { title: 'Register Patient', titleKey: 'Page_Register_Patient', permission: 'hms_patients_add' } },
            { path: 'hospital/patients/:id(\\d+)/edit', name: 'hospital-patient-edit', component: PatientForm, meta: { title: 'Edit Patient', titleKey: 'Page_Edit_Patient', permission: 'hms_patients_edit' } },
            { path: 'hospital/patients/:id(\\d+)', name: 'hospital-patient', component: PatientDetails, meta: { title: 'Patient Record', titleKey: 'Page_Patient_Record', permission: 'hms_patients_view' } },
            { path: 'hospital/doctors', name: 'hospital-doctors', component: HospitalDoctors, meta: { title: 'Doctors', titleKey: 'Doctors', permission: 'hms_doctors' } },
            { path: 'hospital/departments', name: 'hospital-departments', component: HospitalDepartments, meta: { title: 'Hospital Departments', titleKey: 'Page_Hospital_Departments', permission: 'hms_departments' } },
            { path: 'hospital/appointments', name: 'hospital-appointments', component: HospitalAppointments, meta: { title: 'Appointments', titleKey: 'Appointments', permission: 'hms_appointments' } },
            { path: 'hospital/visits', name: 'hospital-visits', component: HospitalVisits, meta: { title: 'Consultations', titleKey: 'Consultations', permission: 'hms_visits' } },
            { path: 'hospital/visits/create', name: 'hospital-visit-create', component: HospitalVisitForm, meta: { title: 'New Consultation', titleKey: 'Page_New_Consultation', permission: 'hms_visits' } },
            { path: 'hospital/visits/:id(\\d+)/edit', name: 'hospital-visit-edit', component: HospitalVisitForm, meta: { title: 'Edit Consultation', titleKey: 'Page_Edit_Consultation', permission: 'hms_visits' } },
            { path: 'hospital/wards', name: 'hospital-wards', component: HospitalWards, meta: { title: 'Wards & Beds', titleKey: 'Wards & Beds', permission: 'hms_wards' } },
            { path: 'hospital/admissions', name: 'hospital-admissions', component: HospitalAdmissions, meta: { title: 'Admissions', titleKey: 'Admissions', permission: 'hms_admissions' } },
            { path: 'hospital/lab-tests', name: 'hospital-lab-tests', component: HospitalLabTests, meta: { title: 'Lab Test Catalogue', titleKey: 'Hosp_Lab_Test_Catalogue', permission: 'hms_lab' } },
            { path: 'hospital/lab-orders', name: 'hospital-lab-orders', component: HospitalLabOrders, meta: { title: 'Lab Orders', titleKey: 'Lab Orders', permission: 'hms_lab' } },
            { path: 'hospital/invoices', name: 'hospital-invoices', component: HospitalInvoices, meta: { title: 'Hospital Billing', titleKey: 'Page_Hospital_Billing', permission: 'hms_billing' } },
            { path: 'hospital/reports', name: 'hospital-reports', component: HospitalReports, meta: { title: 'Hospital Reports', titleKey: 'Hospital Reports', permission: 'hms_reports' } },
            // School Management. `create` is declared before the :id routes so
            // it is never read as a record id.
            { path: 'school/dashboard', name: 'school-dashboard', component: SchoolDashboard, meta: { title: 'School Dashboard', titleKey: 'School_Dashboard', permission: 'school_dashboard' } },
            { path: 'school/students', name: 'school-students', component: SchoolStudents, meta: { title: 'Students', titleKey: 'Students', permission: 'school_students_view' } },
            { path: 'school/students/create', name: 'school-student-create', component: SchoolStudentForm, meta: { title: 'Admit Student', titleKey: 'Page_Admit_Student', permission: 'school_students_add' } },
            { path: 'school/students/:id(\\d+)/edit', name: 'school-student-edit', component: SchoolStudentForm, meta: { title: 'Edit Student', titleKey: 'Page_Edit_Student', permission: 'school_students_edit' } },
            { path: 'school/students/:id(\\d+)', name: 'school-student', component: SchoolStudentDetails, meta: { title: 'Student Record', titleKey: 'Page_Student_Record', permission: 'school_students_view' } },
            { path: 'school/teachers', name: 'school-teachers', component: SchoolTeachers, meta: { title: 'Teachers', titleKey: 'Teachers', permission: 'school_teachers' } },
            { path: 'school/academics', name: 'school-academics', component: SchoolAcademics, meta: { title: 'Academic Setup', titleKey: 'Academic Setup', permission: 'school_academics' } },
            { path: 'school/enrollments', name: 'school-enrollments', component: SchoolEnrollments, meta: { title: 'Enrolment', titleKey: 'Enrolment', permission: 'school_enrollment' } },
            { path: 'school/attendance', name: 'school-attendance', component: SchoolAttendance, meta: { title: 'Attendance', titleKey: 'Attendance', permission: 'school_attendance' } },
            { path: 'school/timetable', name: 'school-timetable', component: SchoolTimetable, meta: { title: 'Timetable', titleKey: 'Timetable', permission: 'school_timetable' } },
            { path: 'school/exams', name: 'school-exams', component: SchoolExams, meta: { title: 'Exams & Results', titleKey: 'Exams & Results', permission: 'school_exams' } },
            { path: 'school/fees', name: 'school-fees', component: SchoolFees, meta: { title: 'School Fees', titleKey: 'Page_School_Fees', permission: 'school_fees' } },
            { path: 'school/reports', name: 'school-reports', component: SchoolReports, meta: { title: 'School Reports', titleKey: 'School Reports', permission: 'school_reports' } },
            { path: 'hrm/employees', name: 'employees', component: Employees, meta: { title: 'Employees', titleKey: 'Employees', permission: 'view_employee' } },
            { path: 'hrm/employees/create', name: 'employee-create', component: EmployeeForm, meta: { title: 'Add Employee', titleKey: 'Add_Employee', permission: 'add_employee' } },
            { path: 'hrm/employees/:id/edit', name: 'employee-edit', component: EmployeeForm, meta: { title: 'Edit Employee', titleKey: 'Page_Edit_Employee', permission: 'edit_employee' } },
            { path: 'hrm/employees/:id(\\d+)/details', name: 'employee-details', component: EmployeeDetails, meta: { title: 'Employee Details', titleKey: 'Employee_Details', permission: 'view_employee' } },
            { path: 'hrm/attendance', name: 'attendance', component: Attendance, meta: { title: 'Attendance', titleKey: 'Attendance', permission: 'attendance' } },
            { path: 'hrm/leaves', name: 'leaves', component: Leaves, meta: { title: 'Leave Requests', titleKey: 'Page_Leave_Requests', permission: 'leave' } },
            { path: 'hrm/leave-types', name: 'leave-types', component: LeaveTypes, meta: { title: 'Leave Types', titleKey: 'Page_Leave_Types', permission: 'leave' } },
            { path: 'hrm/payrolls', name: 'payrolls', component: Payrolls, meta: { title: 'Payroll', titleKey: 'Payroll', permission: 'payroll' } },
            { path: 'marketing/dashboard', name: 'marketing-dashboard', component: MarketingDashboard, meta: { title: 'Marketing Dashboard', titleKey: 'Marketing_Dashboard', permission: 'marketing_dashboard' } },
            { path: 'marketing/campaigns', name: 'marketing-campaigns', component: Campaigns, meta: { title: 'Campaigns', titleKey: 'Campaigns', permission: 'marketing_campaigns' } },
            { path: 'marketing/campaigns/create', name: 'marketing-campaign-create', component: CampaignForm, meta: { title: 'Create Campaign', titleKey: 'Create_Campaign', permission: 'marketing_campaigns' } },
            { path: 'marketing/campaigns/:id/edit', name: 'marketing-campaign-edit', component: CampaignForm, meta: { title: 'Edit Campaign', titleKey: 'Edit_Campaign', permission: 'marketing_campaigns' } },
            { path: 'marketing/campaigns/:id', name: 'marketing-campaign-details', component: CampaignDetails, meta: { title: 'Campaign Details', titleKey: 'Campaign_Details', permission: 'marketing_campaigns' } },
            { path: 'marketing/segments', name: 'marketing-segments', component: Segments, meta: { title: 'Customer Segments', titleKey: 'Customer_Segments', permission: 'marketing_segments' } },
            { path: 'marketing/templates/:type(sms|whatsapp|email)', name: 'marketing-templates', component: MarketingTemplates, meta: { title: 'Marketing Templates', titleKey: 'Marketing_Templates', permission: 'marketing_templates' } },
            { path: 'marketing/reports', name: 'marketing-reports', component: MarketingReports, meta: { title: 'Marketing Reports', titleKey: 'Marketing_Reports', permission: 'marketing_reports' } },
            { path: 'marketing/settings', name: 'marketing-settings', component: MarketingSettings, meta: { title: 'Marketing Settings', titleKey: 'Marketing_Settings', permission: 'marketing_settings' } },
            { path: 'sales', name: 'sales', component: Sales, meta: { title: 'Sales', titleKey: 'Sales', permission: 'Sales_view' } },
            { path: 'sales/create', name: 'sale-create', component: SaleForm, meta: { title: 'Add Sale', titleKey: 'Page_Add_Sale', permission: 'Sales_add' } },
            { path: 'sales/import', name: 'sales-import', component: ImportSales, meta: { title: 'Import Sales', titleKey: 'Import_Sales', permission: 'Sales_add' } },
            { path: 'sales/:id(\\d+)/edit', name: 'sale-edit', component: SaleForm, meta: { title: 'Edit Sale', titleKey: 'EditSale', permission: 'Sales_edit' } },
            { path: 'sales/from-quotation/:id(\\d+)', name: 'sale-from-quotation', component: SaleForm, meta: { title: 'Create Sale', titleKey: 'AddSale', permission: 'Sales_add', mode: 'convert' } },
            { path: 'sales/:id(\\d+)', name: 'sale-details', component: SaleDetails, meta: { title: 'Sale Detail', titleKey: 'SaleDetail', permission: 'Sales_view' } },
            { path: 'purchases', name: 'purchases', component: Purchases, meta: { title: 'Purchases', titleKey: 'Purchases', permission: 'Purchases_view' } },
            { path: 'purchases/create', name: 'purchase-create', component: PurchaseForm, meta: { title: 'Add Purchase', titleKey: 'Page_Add_Purchase', permission: 'Purchases_add' } },
            { path: 'purchases/import', name: 'purchases-import', component: ImportPurchases, meta: { title: 'Import Purchases', titleKey: 'Import_Purchases', permission: 'Purchases_add' } },
            { path: 'purchases/reorder', name: 'purchases-reorder', component: ReorderSuggestions, meta: { title: 'Reorder suggestions', titleKey: 'Reorder_Suggestions', permission: 'Purchases_add' } },
            { path: 'purchases/:id(\\d+)/edit', name: 'purchase-edit', component: PurchaseForm, meta: { title: 'Edit Purchase', titleKey: 'EditPurchase', permission: 'Purchases_edit' } },
            { path: 'purchases/:id(\\d+)', name: 'purchase-details', component: PurchaseDetails, meta: { title: 'Purchase Detail', titleKey: 'PurchaseDetail', permission: 'Purchases_view' } },
            { path: 'quotations', name: 'quotations', component: Quotations, meta: { title: 'Quotations', titleKey: 'Quotations', permission: 'Quotations_view' } },
            { path: 'quotations/create', name: 'quotation-create', component: QuotationForm, meta: { title: 'Add Quotation', titleKey: 'Page_Add_Quotation', permission: 'Quotations_add' } },
            { path: 'quotations/:id(\\d+)/edit', name: 'quotation-edit', component: QuotationForm, meta: { title: 'Edit Quotation', titleKey: 'EditQuote', permission: 'Quotations_edit' } },
            { path: 'quotations/:id(\\d+)', name: 'quotation-details', component: QuotationDetails, meta: { title: 'Quotation Detail', titleKey: 'Page_Quotation_Detail', permission: 'Quotations_view' } },
            { path: 'sale-returns', name: 'sale-returns', component: SaleReturns, meta: { title: 'Sales Return', titleKey: 'SalesReturn', permission: 'Sale_Returns_view' } },
            { path: 'sale-returns/create/:saleId(\\d+)', name: 'sale-return-create', component: SaleReturnForm, meta: { title: 'Create Sale Return', titleKey: 'CreateSaleReturn', permission: 'Sale_Returns_add' } },
            { path: 'sale-returns/:id(\\d+)/edit/:saleId(\\d+)', name: 'sale-return-edit', component: SaleReturnForm, meta: { title: 'Edit Sale Return', titleKey: 'EditSaleReturn', permission: 'Sale_Returns_edit' } },
            { path: 'sale-returns/:id(\\d+)', name: 'sale-return-details', component: SaleReturnDetails, meta: { title: 'Sale Return Detail', titleKey: 'Page_Sale_Return_Detail', permission: 'Sale_Returns_view' } },
            { path: 'purchase-returns', name: 'purchase-returns', component: PurchaseReturns, meta: { title: 'Purchases Return', titleKey: 'PurchasesReturn', permission: 'Purchase_Returns_view' } },
            { path: 'purchase-returns/create/:purchaseId(\\d+)', name: 'purchase-return-create', component: PurchaseReturnForm, meta: { title: 'Create Purchase Return', titleKey: 'CreatePurchaseReturn', permission: 'Purchase_Returns_add' } },
            { path: 'purchase-returns/:id(\\d+)/edit/:purchaseId(\\d+)', name: 'purchase-return-edit', component: PurchaseReturnForm, meta: { title: 'Edit Purchase Return', titleKey: 'EditPurchaseReturn', permission: 'Purchase_Returns_edit' } },
            { path: 'purchase-returns/:id(\\d+)', name: 'purchase-return-details', component: PurchaseReturnDetails, meta: { title: 'Purchase Return Detail', titleKey: 'Page_Purchase_Return_Detail', permission: 'Purchase_Returns_view' } },
            { path: 'adjustments', name: 'adjustments', component: Adjustments, meta: { title: 'Adjustments', titleKey: 'Adjustments', permission: 'adjustment_view' } },
            { path: 'adjustments/create', name: 'adjustment-create', component: AdjustmentForm, meta: { title: 'Create Adjustment', titleKey: 'CreateAdjustment', permission: 'adjustment_add' } },
            { path: 'adjustments/:id(\\d+)/edit', name: 'adjustment-edit', component: AdjustmentForm, meta: { title: 'Edit Adjustment', titleKey: 'Page_Edit_Adjustment', permission: 'adjustment_edit' } },
            { path: 'adjustments/:id(\\d+)', name: 'adjustment-details', component: AdjustmentDetails, meta: { title: 'Adjustment Detail', titleKey: 'AdjustmentDetail', permission: 'adjustment_view' } },
            { path: 'transfers', name: 'transfers', component: Transfers, meta: { title: 'Transfers', titleKey: 'Transfers', permission: 'transfer_view' } },
            { path: 'transfers/create', name: 'transfer-create', component: TransferForm, meta: { title: 'Create Transfer', titleKey: 'CreateTransfer', permission: 'transfer_add' } },
            { path: 'transfers/:id(\\d+)/edit', name: 'transfer-edit', component: TransferForm, meta: { title: 'Edit Transfer', titleKey: 'EditTransfer', permission: 'transfer_edit' } },
            { path: 'transfers/:id(\\d+)', name: 'transfer-details', component: TransferDetails, meta: { title: 'Transfer Detail', titleKey: 'TransferDetail', permission: 'transfer_view' } },
            { path: 'products', name: 'products', component: Products, meta: { title: 'Products', titleKey: 'Products', permission: 'products_view' } },
            { path: 'products/count-stock', name: 'count-stock', component: CountStock, meta: { title: 'Count Stock', titleKey: 'CountStock', permission: 'count_stock' } },
            { path: 'products/batches', name: 'batches', component: Batches, meta: { title: 'Batches', titleKey: 'Batches', permission: ['view_batches', 'batch_view'] } },
            { path: 'products/import', name: 'products-import', component: ImportProducts, meta: { title: 'Import Products', titleKey: 'Page_Import_Products', permission: 'product_import' } },
            { path: 'products/import-update', name: 'products-import-update', component: ImportProductsUpdate, meta: { title: 'Import Products (Update Only)', titleKey: 'Imp_Products_Update_Only_Title', permission: 'product_import' } },
            { path: 'products/opening-stock-import', name: 'opening-stock-import', component: OpeningStockImport, meta: { title: 'Opening Stock', titleKey: 'OpeningStock', permission: 'opening_stock_import' } },
            { path: 'products/barcode', name: 'products-barcode', component: Barcode, meta: { title: 'Print Barcode', titleKey: 'Page_Print_Barcode', permission: 'barcode_view' } },
            // Accounting
            { path: 'accounts', name: 'accounts', component: Accounts, meta: { title: 'Accounts', titleKey: 'Accounts', permission: 'account' } },
            { path: 'transfer-money', name: 'transfer-money', component: TransferMoney, meta: { title: 'Transfer Money', titleKey: 'Page_Transfer_Money', permission: 'transfer_money' } },
            { path: 'expenses', name: 'expenses', component: Expenses, meta: { title: 'Expenses', titleKey: 'Expenses', permission: 'expense_view' } },
            { path: 'expenses/create', name: 'expense-create', component: ExpenseForm, meta: { title: 'Create Expense', titleKey: 'Create_Expense', permission: 'expense_add' } },
            { path: 'expenses/:id(\\d+)/edit', name: 'expense-edit', component: ExpenseForm, meta: { title: 'Edit Expense', titleKey: 'Edit_Expense', permission: 'expense_edit' } },
            { path: 'expenses/categories', name: 'expense-categories', component: ExpenseCategories, meta: { title: 'Expense Categories', titleKey: 'Page_Expense_Categories', permission: 'expense_view' } },
            // Damages (legacy gates edit with adjustment_edit; list/create with damage_view)
            { path: 'damages', name: 'damages', component: Damages, meta: { title: 'Damages', titleKey: 'Damages', permission: 'damage_view' } },
            { path: 'damages/create', name: 'damage-create', component: DamageForm, meta: { title: 'Create Damage', titleKey: 'Create_Damage', permission: 'damage_view' } },
            { path: 'damages/:id(\\d+)/edit', name: 'damage-edit', component: DamageForm, meta: { title: 'Edit Damage', titleKey: 'Edit_Damage', permission: 'adjustment_edit' } },
            { path: 'shipments', name: 'shipments', component: Shipments, meta: { title: 'Shipments', titleKey: 'Shipments', permission: 'shipment' } },
            { path: 'offline-sales', name: 'offline-sales', component: OfflineSales, meta: { title: 'Offline Sales', titleKey: 'Offline_Sales', permission: 'pos_offline_sales' } },
            { path: 'customers-without-login', name: 'customers-without-login', component: CustomersWithoutLogin, meta: { title: 'Customers without Login', titleKey: 'Customers_without_Login', permission: 'Customers_view' } },
            { path: 'customers-with-login', name: 'customers-with-login', component: CustomersWithLogin, meta: { title: 'Customers with Login', titleKey: 'Customers_with_Login', permission: 'Customers_view' } },
            { path: 'loyalty/rewards', name: 'loyalty-rewards', component: LoyaltyRewards, meta: { title: 'Loyalty Rewards', titleKey: 'Loyalty_Rewards', permission: 'loyalty_rewards' } },
            { path: 'real-time-sales-counter', name: 'real-time-sales-counter', component: RealTimeSalesCounter, meta: { title: 'Real-time Sales Counter', titleKey: 'Real_time_Sales_Counter', permission: 'real_time_sales_counter' } },
            { path: 'customer-display/setup', name: 'customer-display-setup', component: CustomerDisplaySetup, meta: { title: 'Customer Screen', titleKey: 'Customer_Screen', permission: 'customer_display_screen_setup' } },
            { path: 'products/vehicle-fitment', name: 'vehicle-fitment', component: VehicleCatalog, meta: { title: 'Vehicle Fitment', titleKey: 'Vehicle_Fitment', permission: 'products_view' } },
            { path: 'products/create', name: 'product-create', component: ProductForm, meta: { title: 'Add Product', titleKey: 'Page_Add_Product', permission: 'products_add' } },
            { path: 'products/:id(\\d+)/edit', name: 'product-edit', component: ProductForm, meta: { title: 'Edit Product', titleKey: 'EditProduct', permission: 'products_edit' } },
            { path: 'serial-numbers', name: 'serial-numbers', component: SerialNumbers, meta: { title: 'Serial Numbers', titleKey: 'Page_Serial_Numbers', permission: 'serial_numbers' } },
            { path: 'serial-numbers/:id(\\d+)', name: 'serial-number-details', component: SerialNumberDetails, meta: { title: 'Serial Number', titleKey: 'Serial_Number', permission: 'serial_numbers' } },
            { path: 'products/:id(\\d+)', name: 'product-details', component: ProductDetails, meta: { title: 'Product Details', titleKey: 'ProductDetails', permission: 'products_view' } },
            { path: 'deposits', name: 'deposits', component: Deposits, meta: { title: 'Deposits', titleKey: 'Deposits', permission: 'deposit_view' } },
            { path: 'deposits/create', name: 'deposit-create', component: DepositForm, meta: { title: 'Create Deposit', titleKey: 'Page_Create_Deposit', permission: 'deposit_add' } },
            { path: 'deposits/:id/edit', name: 'deposit-edit', component: DepositForm, meta: { title: 'Edit Deposit', titleKey: 'Page_Edit_Deposit', permission: 'deposit_edit' } },
            { path: 'deposits/categories', name: 'deposit-categories', component: DepositCategories, meta: { title: 'Deposit Categories', titleKey: 'Page_Deposit_Categories', permission: 'deposit_view' } },
            { path: 'settings/payment-methods', name: 'payment-methods', component: PaymentMethods, meta: { title: 'Payment Methods', titleKey: 'Payment_Methods', permission: 'payment_methods' } },
            // Rack locations now live in a tab on the Warehouses page.
            { path: 'settings/warehouse-locations', redirect: '/warehouses' },
            { path: 'settings/payment-gateway', name: 'payment-gateway', component: PaymentGateway, meta: { title: 'Payment Gateway', titleKey: 'Payment_Gateway', permission: 'payment_gateway' } },
            { path: 'settings/mail', name: 'mail-settings', component: MailSettings, meta: { title: 'Mail Settings', titleKey: 'mail_settings', permission: 'mail_settings' } },
            { path: 'settings/login-devices', name: 'login-devices', component: LoginDevices, meta: { title: 'Login Devices', titleKey: 'Page_Login_Devices', permission: 'login_device_management' } },
            { path: 'settings/system-health', name: 'system-health', component: SystemHealth, meta: { title: 'System Health', titleKey: 'System_Health', permission: 'system_health_view' } },
            { path: 'settings/audit-log', name: 'audit-log', component: AuditLog, meta: { title: 'Audit Log', titleKey: 'Set_Audit_Log', permission: 'audit_log' } },
            { path: 'ai-assistant', name: 'ai-assistant', component: AiAssistant, meta: { title: 'AI Assistant', titleKey: 'AI_Assistant', permission: 'ai_assistant' } },
            { path: 'settings/sms-templates', name: 'sms-templates', component: SmsTemplates, meta: { title: 'SMS Templates', titleKey: 'sms_templates', permission: 'notification_template' } },
            { path: 'settings/email-templates', name: 'email-templates', component: EmailTemplates, meta: { title: 'Email Templates', titleKey: 'email_templates', permission: 'notification_template' } },
            // Legacy route has no permission gate — keep it open the same way.
            { path: 'settings/custom-fields', name: 'custom-fields', component: CustomFields, meta: { title: 'Custom Fields', titleKey: 'CustomFields' } },
            { path: 'settings/backup', name: 'backup', component: Backup, meta: { title: 'Backup', titleKey: 'Backup', permission: 'backup' } },
            { path: 'settings/pos', name: 'pos-settings', component: PosSettings, meta: { title: 'POS Settings', titleKey: 'Pos_Settings', permission: 'pos_settings' } },
            { path: 'settings/appearance', name: 'appearance-settings', component: AppearanceSettings, meta: { title: 'Appearance', titleKey: 'Set_Appearance', permission: 'appearance_settings' } },
            { path: 'settings/pwa', name: 'pwa-settings', component: PwaSettings, meta: { title: 'PWA Settings', titleKey: 'PWA_Settings', permission: 'appearance_settings' } },
            { path: 'settings/mobile-app', name: 'mobile-app-settings', component: MobileAppSettings, meta: { title: 'Mobile App Settings', titleKey: 'Page_Mobile_App_Settings', permission: 'appearance_settings' } },
            { path: 'settings/sms', name: 'sms-settings', component: SmsSettings, meta: { title: 'SMS Settings', titleKey: 'Page_SMS_Settings', permission: 'sms_settings' } },
            { path: 'settings/languages', name: 'languages', component: Languages, meta: { title: 'Languages', titleKey: 'Languages', permission: 'translations_settings' } },
            { path: 'settings/translations/:locale', name: 'translations-view', component: TranslationsView, meta: { title: 'Translations', titleKey: 'Translations', permission: 'translations_settings' } },
            { path: 'settings/webhooks', name: 'webhooks', component: Webhooks, meta: { title: 'Webhooks', titleKey: 'Webhooks', permission: 'webhooks_view' } },
            { path: 'settings/pos-receipt', name: 'pos-receipt', component: PosReceipt, meta: { title: 'POS Receipt', titleKey: 'POS_Receipt', permission: 'pos_settings' } },
            { path: 'settings/modules', name: 'module-settings', component: ModuleSettings, meta: { title: 'Module Settings', titleKey: 'module_settings', permission: 'module_settings' } },
            { path: 'settings/business-modules', name: 'business-modules', component: Modules, meta: { title: 'Modules', titleKey: 'Modules', permission: ['business_modules', 'setting_system'] } },
            { path: 'settings/update', name: 'update-settings', component: UpdateSettings, meta: { title: 'Update Settings', titleKey: 'Page_Update_Settings', permission: 'update_settings' } },
            { path: 'settings/system', name: 'system-settings', component: SystemSettings, meta: { title: 'System Settings', titleKey: 'SystemSettings', permission: 'setting_system' } },
            { path: 'settings/quickbooks', name: 'quickbooks-sync', component: QuickBooksSync, meta: { title: 'QuickBooks Sync', titleKey: 'Page_QuickBooks_Sync', permission: 'quickbooks_settings' } },
            { path: 'settings/zatca', name: 'zatca-settings', component: ZatcaSettings, meta: { title: 'ZATCA E-Invoicing', titleKey: 'Zatca_E_Invoicing', permission: 'zatca_settings' } },
            { path: 'woocommerce', name: 'woocommerce-settings', component: WooCommerceSettings, meta: { title: 'WooCommerce', titleKey: 'WooCommerce', permission: 'woocommerce_settings' } },
            { path: 'woocommerce/guide', name: 'woocommerce-guide', component: WooCommerceGuidePage, meta: { title: 'WooCommerce Guide', titleKey: 'Page_WooCommerce_Guide', permission: 'woocommerce_settings' } },
            // Integrations platform — hub is visible with ANY connector permission.
            { path: 'integrations', name: 'integrations-hub', component: IntegrationsHub, meta: { title: 'Integrations', titleKey: 'Integrations', permission: ['woocommerce_settings', 'shopify_stores', 'shopify_sync', 'shopify_logs', 'salla_settings', 'prestashop_settings', 'jumia_settings', 'quickbooks_settings', 'xero_settings', 'google_sheets_settings', 'mailchimp_settings', 'slack_settings', 'telegram_settings', 'whatsapp_settings', 'webhooks_view', 'courier_settings'] } },
            { path: 'integrations/slack', name: 'slack-settings', component: SlackSettings, meta: { title: 'Slack', titleKey: 'Page_Slack', permission: 'slack_settings' } },
            { path: 'integrations/telegram', name: 'telegram-settings', component: TelegramSettings, meta: { title: 'Telegram', titleKey: 'Page_Telegram', permission: 'telegram_settings' } },
            { path: 'integrations/whatsapp', name: 'whatsapp-settings', component: WhatsAppSettings, meta: { title: 'WhatsApp', titleKey: 'WhatsApp', permission: 'whatsapp_settings' } },
            { path: 'integrations/salla', name: 'salla-settings', component: SallaSettings, meta: { title: 'Salla', titleKey: 'Page_Salla', permission: 'salla_settings' } },
            { path: 'integrations/xero', name: 'xero-settings', component: XeroSettings, meta: { title: 'Xero', titleKey: 'Page_Xero', permission: 'xero_settings' } },
            { path: 'integrations/prestashop', name: 'prestashop-settings', component: PrestashopSettings, meta: { title: 'PrestaShop', titleKey: 'Page_PrestaShop', permission: 'prestashop_settings' } },
            { path: 'integrations/google-sheets', name: 'google-sheets-settings', component: GoogleSheetsSettings, meta: { title: 'Google Sheets', titleKey: 'Page_Google_Sheets', permission: 'google_sheets_settings' } },
            { path: 'integrations/mailchimp', name: 'mailchimp-settings', component: MailchimpSettings, meta: { title: 'Mailchimp', titleKey: 'Page_Mailchimp', permission: 'mailchimp_settings' } },
            { path: 'integrations/jumia', name: 'jumia-settings', component: JumiaSettings, meta: { title: 'Jumia', titleKey: 'Page_Jumia', permission: 'jumia_settings' } },
            { path: ':pathMatch(.*)*', name: 'not-found', component: NotFound, meta: { title: 'Not found', titleKey: 'Page_Not_Found' } },
        ],
    },
];

const router = createRouter({
    // Served under /next/ by Laravel (catch-all route before the SPA wildcard).
    history: createWebHistory('/next/'),
    routes,
});

/** Browser-tab title in the active language (English `title` is the fallback). */
export function applyTitle(to = router.currentRoute.value) {
    if (!to || !to.meta.title) return;
    const title = to.meta.titleKey ? t(to.meta.titleKey, to.meta.title) : to.meta.title;
    const suffix = window.__PAGE_TITLE_SUFFIX__ || '';
    document.title = suffix ? `${title} — ${suffix}` : title;
}

// Language switch: retitle the current tab.
watch(() => i18n.global.locale.value, () => applyTitle());

router.beforeEach(async to => {
    progressStart();
    applyTitle(to);
    if (to.meta.skipAuth) return true;

    const auth = useAuthStore();
    await auth.load();
    // `permission` may be a single key or a list of acceptable ones — some
    // pages are reachable under either of two permissions (e.g. batches under
    // view_batches OR batch_view), matching the sidebar gate and the backend.
    if (to.meta.permission) {
        const perms = Array.isArray(to.meta.permission) ? to.meta.permission : [to.meta.permission];
        if (!perms.some(p => auth.can(p))) {
            return { name: 'not-found' };
        }
    }
    // Pages of a module the admin switched off (Settings → Modules) are
    // unreachable even by direct URL, mirroring the sidebar filter.
    const moduleKey = moduleKeyForPath(to.path);
    if (moduleKey && !auth.moduleEnabled(moduleKey)) {
        return { name: 'not-found' };
    }
    return true;
});

// Covers both the guard and the lazy chunk load (afterEach fires only once the
// chunk resolved). onError catches failed chunks/guards so the bar never hangs.
router.afterEach(() => progressDone());
router.onError((error, to) => {
    progressDone();
    // Stale-deploy recovery: after a rebuild the old hashed chunks are deleted,
    // so navigating to a not-yet-visited section 404s on its dynamic import.
    // Reload the browser AT THE TARGET route — reloading in place (the
    // vite:preloadError fallback in main.js) looked like "navigation is stuck":
    // the page refreshed but stayed on the section the user was leaving.
    const msg = String((error && error.message) || '');
    if (/Failed to fetch dynamically imported module|error loading dynamically imported module|Importing a module script failed|ChunkLoadError/i.test(msg)) {
        if (sessionStorage.getItem('stocky-chunk-reload') === '1') return;
        sessionStorage.setItem('stocky-chunk-reload', '1');
        window.__stockyChunkRedirect = true;
        try {
            window.location.assign(router.resolve(to && to.fullPath ? to.fullPath : '/').href);
        } catch (e) {
            window.location.reload();
        }
    }
});

export default router;
