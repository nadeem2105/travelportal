<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Panel — every sensitive action is permission-guarded server-side
|--------------------------------------------------------------------------
*/

Route::middleware('guest:admin')->group(function () {
    Route::get('admin/login', [Admin\AuthController::class, 'showLogin'])->name('admin.login');
    Route::post('admin/login', [Admin\AuthController::class, 'login'])->middleware('throttle:20,1')->name('admin.login.attempt');
});

Route::middleware('admin.auth')->prefix('admin')->name('admin.')->group(function () {
    Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');

    // Dashboard (all stats)
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Bookings
    Route::get('bookings', [Admin\BookingController::class, 'index'])->middleware('admin.permission:view_bookings')->name('bookings.index');
    Route::get('bookings/create', [Admin\BookingController::class, 'create'])->middleware('admin.permission:edit_booking')->name('bookings.create');
    Route::post('bookings', [Admin\BookingController::class, 'store'])->middleware('admin.permission:edit_booking')->name('bookings.store');
    Route::get('bookings/{booking}', [Admin\BookingController::class, 'show'])->middleware('admin.permission:view_bookings')->name('bookings.show');
    Route::get('bookings/{booking}/invoice', [Admin\BookingController::class, 'invoice'])->middleware('admin.permission:view_bookings')->name('bookings.invoice');
    Route::get('bookings/{booking}/itinerary', [Admin\BookingController::class, 'itinerary'])->middleware('admin.permission:view_bookings')->name('bookings.itinerary');
    Route::post('bookings/{booking}/status', [Admin\BookingController::class, 'updateStatus'])->middleware('admin.permission:edit_booking')->name('bookings.status');
    Route::post('bookings/{booking}/assign-driver', [Admin\BookingController::class, 'assignDriver'])->middleware('admin.permission:edit_booking')->name('bookings.assign-driver');
    Route::post('bookings/{booking}/resend-email', [Admin\BookingController::class, 'resendConfirmationEmail'])->middleware('admin.permission:edit_booking')->name('bookings.resend-email');
    Route::post('bookings/{booking}/send-whatsapp-invoice', [Admin\BookingController::class, 'sendWhatsAppInvoice'])->middleware('admin.permission:edit_booking')->name('bookings.send-whatsapp-invoice');
    Route::post('bookings/{booking}/notes', [Admin\BookingController::class, 'addNotes'])->middleware('admin.permission:edit_booking')->name('bookings.notes');
    Route::post('bookings/{booking}/cancel', [Admin\BookingController::class, 'cancel'])->middleware('admin.permission:cancel_booking')->name('bookings.cancel');
    Route::post('refunds/{refund}/process', [Admin\RefundController::class, 'process'])->middleware('admin.permission:refund_booking')->name('refunds.process');
    Route::post('refunds/{refund}/reject', [Admin\RefundController::class, 'reject'])->middleware('admin.permission:refund_booking')->name('refunds.reject');
    Route::get('refunds', [Admin\RefundController::class, 'index'])->middleware('admin.permission:view_bookings')->name('refunds.index');
    Route::get('reconciliation', [Admin\RefundController::class, 'reconciliation'])->middleware('admin.permission:view_bookings')->name('reconciliation');
    Route::get('payments', [Admin\RefundController::class, 'payments'])->middleware('admin.permission:manage_payments')->name('payments.index');
    Route::get('bookings-export', [Admin\BookingController::class, 'export'])->middleware('admin.permission:view_bookings')->name('bookings.export');
    Route::get('payments-export', [Admin\RefundController::class, 'exportPayments'])->middleware('admin.permission:manage_payments')->name('payments.export');
    Route::get('refunds-export', [Admin\RefundController::class, 'export'])->middleware('admin.permission:view_bookings')->name('refunds.export');

    // Customers
    Route::get('customers', [Admin\CustomerController::class, 'index'])->middleware('admin.permission:manage_customers')->name('customers.index');
    Route::get('customers-export', [Admin\CustomerController::class, 'export'])->middleware('admin.permission:manage_customers')->name('customers.export');
    Route::get('customers/{user}', [Admin\CustomerController::class, 'show'])->middleware('admin.permission:manage_customers')->name('customers.show');
    Route::post('customers/{user}/toggle', [Admin\CustomerController::class, 'toggle'])->middleware('admin.permission:manage_customers')->name('customers.toggle');

    // B2B Agents
    Route::get('agents', [Admin\AgentController::class, 'index'])->middleware('admin.permission:manage_agents')->name('agents.index');
    Route::get('agents/create', [Admin\AgentController::class, 'create'])->middleware('admin.permission:manage_agents')->name('agents.create');
    Route::post('agents', [Admin\AgentController::class, 'store'])->middleware('admin.permission:manage_agents')->name('agents.store');
    Route::get('agents/{agent}', [Admin\AgentController::class, 'show'])->middleware('admin.permission:manage_agents')->name('agents.show');
    Route::post('agents/{agent}/status', [Admin\AgentController::class, 'updateStatus'])->middleware('admin.permission:manage_agents')->name('agents.status');
    Route::post('agents/{agent}/balance', [Admin\AgentController::class, 'adjustBalance'])->middleware('admin.permission:manage_agents')->name('agents.balance');
    Route::post('agents/{agent}/password', [Admin\AgentController::class, 'setPassword'])->middleware('admin.permission:manage_agents')->name('agents.password');

    // CRM Analytics & Reports
    Route::get('crm-analytics', [Admin\CrmAnalyticsController::class, 'index'])->middleware('admin.permission:manage_crm')->name('crm-analytics.index');

    // Ad Integrations (Meta / Google lead webhooks status)
    Route::get('ad-integrations', [Admin\AdIntegrationsController::class, 'index'])->middleware('admin.permission:manage_crm')->name('ad-integrations.index');

    // ── Marketing / Advertising platform ──
    Route::get('marketing', [Admin\MarketingController::class, 'overview'])->middleware('admin.permission:marketing.view')->name('marketing.overview');
    Route::get('marketing/accounts', [Admin\MarketingController::class, 'accounts'])->middleware('admin.permission:marketing.accounts')->name('marketing.accounts');
    // DB-backed provider credentials (no .env). Secrets encrypted at rest.
    Route::get('marketing/settings', [Admin\MarketingController::class, 'settings'])->middleware('admin.permission:marketing.accounts')->name('marketing.settings');
    Route::post('marketing/settings/{provider}', [Admin\MarketingController::class, 'updateSettings'])->middleware('admin.permission:marketing.accounts')->whereIn('provider', ['google_ads', 'meta_ads'])->name('marketing.settings.update');
    Route::post('marketing/connections/{connection}/check', [Admin\MarketingController::class, 'checkConnection'])->middleware('admin.permission:marketing.accounts')->name('marketing.connections.check');
    Route::get('marketing/{provider}/connect', [Admin\MarketingController::class, 'connect'])->middleware('admin.permission:marketing.accounts')->whereIn('provider', ['google_ads', 'meta_ads'])->name('marketing.connect');
    // OAuth callbacks (redirect URIs configured in provider settings)
    Route::get('marketing/google/callback', [Admin\MarketingController::class, 'callback'])->middleware('admin.permission:marketing.accounts')->defaults('provider', 'google_ads')->name('marketing.google.callback');
    Route::get('marketing/meta/callback', [Admin\MarketingController::class, 'callback'])->middleware('admin.permission:marketing.accounts')->defaults('provider', 'meta_ads')->name('marketing.meta.callback');
    Route::post('marketing/connections/{connection}/sync', [Admin\MarketingController::class, 'syncAccounts'])->middleware('admin.permission:marketing.accounts')->name('marketing.connections.sync');
    Route::post('marketing/connections/{connection}/disconnect', [Admin\MarketingController::class, 'disconnect'])->middleware('admin.permission:marketing.accounts')->name('marketing.connections.disconnect');

    // Campaigns
    Route::get('marketing/campaigns', [Admin\MarketingCampaignController::class, 'index'])->middleware('admin.permission:marketing.view')->name('marketing.campaigns.index');
    Route::get('marketing/campaigns/create', [Admin\MarketingCampaignController::class, 'create'])->middleware('admin.permission:marketing.campaigns.create')->name('marketing.campaigns.create');
    Route::get('marketing/campaigns/from-package/{package}', [Admin\MarketingCampaignController::class, 'createFromProduct'])->middleware('admin.permission:marketing.campaigns.create')->name('marketing.campaigns.from-package');
    Route::post('marketing/campaigns', [Admin\MarketingCampaignController::class, 'store'])->middleware('admin.permission:marketing.campaigns.create')->name('marketing.campaigns.store');
    Route::get('marketing/campaigns/{campaign}', [Admin\MarketingCampaignController::class, 'show'])->middleware('admin.permission:marketing.view')->name('marketing.campaigns.show');
    Route::post('marketing/campaigns/{campaign}/publish', [Admin\MarketingCampaignController::class, 'publish'])->middleware('admin.permission:marketing.campaigns.publish')->name('marketing.campaigns.publish');
    Route::post('marketing/campaigns/{campaign}/activate', [Admin\MarketingCampaignController::class, 'activate'])->middleware('admin.permission:marketing.campaigns.publish')->name('marketing.campaigns.activate');
    Route::post('marketing/campaigns/{campaign}/pause', [Admin\MarketingCampaignController::class, 'pause'])->middleware('admin.permission:marketing.campaigns.pause')->name('marketing.campaigns.pause');

    // AI Campaign Creator
    Route::get('marketing/ai/campaign', [Admin\AiMarketingController::class, 'campaignForm'])->middleware('admin.permission:marketing.ai')->name('marketing.ai.campaign');
    Route::post('marketing/ai/campaign', [Admin\AiMarketingController::class, 'generateCampaign'])->middleware('admin.permission:marketing.ai')->name('marketing.ai.campaign.generate');

    // ── Ad Creative Studio ──
    Route::get('studio', [Admin\CreativeStudioController::class, 'index'])->middleware('admin.permission:marketing.assets')->name('studio.index');
    Route::get('studio/create', [Admin\CreativeStudioController::class, 'create'])->middleware('admin.permission:marketing.assets')->name('studio.create');
    Route::post('studio', [Admin\CreativeStudioController::class, 'store'])->middleware('admin.permission:marketing.assets')->name('studio.store');
    Route::get('studio/creatives/{creative}', [Admin\CreativeStudioController::class, 'show'])->middleware('admin.permission:marketing.assets')->name('studio.show');
    Route::get('studio/creatives/{creative}/status', [Admin\CreativeStudioController::class, 'status'])->middleware('admin.permission:marketing.assets')->name('studio.status');
    Route::post('studio/creatives/{creative}/regenerate', [Admin\CreativeStudioController::class, 'regenerate'])->middleware('admin.permission:marketing.assets')->name('studio.regenerate');
    Route::post('studio/creatives/{creative}/variations', [Admin\CreativeStudioController::class, 'variations'])->middleware('admin.permission:marketing.assets')->name('studio.variations');
    Route::post('studio/creatives/{creative}/submit', [Admin\CreativeStudioController::class, 'submit'])->middleware('admin.permission:marketing.assets')->name('studio.submit');
    Route::post('studio/creatives/{creative}/approve', [Admin\CreativeStudioController::class, 'approve'])->middleware('admin.permission:marketing.campaigns.publish')->name('studio.approve');
    Route::post('studio/creatives/{creative}/reject', [Admin\CreativeStudioController::class, 'reject'])->middleware('admin.permission:marketing.campaigns.publish')->name('studio.reject');
    Route::post('studio/creatives/{creative}/publish', [Admin\CreativeStudioController::class, 'publish'])->middleware('admin.permission:marketing.campaigns.publish')->name('studio.publish');
    Route::post('studio/creatives/{creative}/duplicate', [Admin\CreativeStudioController::class, 'duplicate'])->middleware('admin.permission:marketing.assets')->name('studio.duplicate');
    Route::delete('studio/creatives/{creative}', [Admin\CreativeStudioController::class, 'destroy'])->middleware('admin.permission:marketing.assets')->name('studio.destroy');
    Route::get('studio/creatives/{creative}/download', [Admin\CreativeStudioController::class, 'download'])->middleware('admin.permission:marketing.assets')->name('studio.download');

    // Brand kits
    Route::get('studio/brand-kits', [Admin\CreativeBrandKitController::class, 'index'])->middleware('admin.permission:marketing.assets')->name('studio.brand-kits.index');
    Route::post('studio/brand-kits', [Admin\CreativeBrandKitController::class, 'store'])->middleware('admin.permission:marketing.assets')->name('studio.brand-kits.store');
    Route::put('studio/brand-kits/{brandKit}', [Admin\CreativeBrandKitController::class, 'update'])->middleware('admin.permission:marketing.assets')->name('studio.brand-kits.update');
    Route::delete('studio/brand-kits/{brandKit}', [Admin\CreativeBrandKitController::class, 'destroy'])->middleware('admin.permission:marketing.assets')->name('studio.brand-kits.destroy');

    // Templates
    Route::get('studio/templates', [Admin\CreativeTemplateController::class, 'index'])->middleware('admin.permission:marketing.assets')->name('studio.templates.index');
    Route::post('studio/templates', [Admin\CreativeTemplateController::class, 'store'])->middleware('admin.permission:marketing.assets')->name('studio.templates.store');
    Route::put('studio/templates/{template}', [Admin\CreativeTemplateController::class, 'update'])->middleware('admin.permission:marketing.assets')->name('studio.templates.update');
    Route::delete('studio/templates/{template}', [Admin\CreativeTemplateController::class, 'destroy'])->middleware('admin.permission:marketing.assets')->name('studio.templates.destroy');

    // Media library
    Route::get('studio/assets', [Admin\CreativeAssetController::class, 'index'])->middleware('admin.permission:marketing.assets')->name('studio.assets.index');
    Route::post('studio/assets', [Admin\CreativeAssetController::class, 'store'])->middleware('admin.permission:marketing.assets')->name('studio.assets.store');
    Route::delete('studio/assets/{asset}', [Admin\CreativeAssetController::class, 'destroy'])->middleware('admin.permission:marketing.assets')->name('studio.assets.destroy');

    // CRM Automations (workflow engine)
    Route::get('automations', [Admin\AutomationController::class, 'index'])->middleware('admin.permission:manage_crm')->name('automations.index');
    Route::get('automations/create', [Admin\AutomationController::class, 'create'])->middleware('admin.permission:manage_crm')->name('automations.create');
    Route::post('automations', [Admin\AutomationController::class, 'store'])->middleware('admin.permission:manage_crm')->name('automations.store');
    Route::get('automations/{automation}/edit', [Admin\AutomationController::class, 'edit'])->middleware('admin.permission:manage_crm')->name('automations.edit');
    Route::put('automations/{automation}', [Admin\AutomationController::class, 'update'])->middleware('admin.permission:manage_crm')->name('automations.update');
    Route::post('automations/{automation}/toggle', [Admin\AutomationController::class, 'toggle'])->middleware('admin.permission:manage_crm')->name('automations.toggle');
    Route::delete('automations/{automation}', [Admin\AutomationController::class, 'destroy'])->middleware('admin.permission:manage_crm')->name('automations.destroy');
    Route::get('automations/{automation}/runs', [Admin\AutomationController::class, 'show'])->middleware('admin.permission:manage_crm')->name('automations.runs');

    // CRM Leads & Quotations
    Route::get('crm', [Admin\CrmController::class, 'index'])->middleware('admin.permission:manage_crm')->name('crm.index');
    Route::post('crm/leads', [Admin\CrmController::class, 'storeLead'])->middleware('admin.permission:manage_crm')->name('crm.lead.store');
    // Static CRM paths must precede the {lead} wildcard to avoid binding collisions.
    Route::post('crm/leads/{lead}/status', [Admin\CrmController::class, 'updateStatus'])->middleware('admin.permission:manage_crm')->name('crm.lead.status');
    Route::post('crm/leads/{lead}/stage', [Admin\CrmController::class, 'changeStage'])->middleware('admin.permission:manage_crm')->name('crm.lead.stage');
    Route::post('crm/leads/{lead}/note', [Admin\CrmController::class, 'storeNote'])->middleware('admin.permission:manage_crm')->name('crm.note.store');
    Route::post('crm/leads/{lead}/followup', [Admin\CrmController::class, 'storeFollowUp'])->middleware('admin.permission:manage_crm')->name('crm.followup.store');
    Route::post('crm/leads/{lead}/quotation', [Admin\CrmController::class, 'storeQuotation'])->middleware('admin.permission:manage_crm')->name('crm.quotation.store');
    Route::get('crm/leads/{lead}', [Admin\CrmController::class, 'showLead'])->middleware('admin.permission:manage_crm')->name('crm.show');
    Route::post('crm/quotations/{quotation}/send', [Admin\CrmController::class, 'sendQuotationEmail'])->middleware('admin.permission:manage_crm')->name('crm.quotation.send');
    Route::get('crm/quotations/{quotation}/pdf', [Admin\CrmController::class, 'viewQuotationPdf'])->middleware('admin.permission:manage_crm')->name('crm.quotation.pdf');
    Route::get('crm/quotations/{quotation}/download', [Admin\CrmController::class, 'downloadQuotationPdf'])->middleware('admin.permission:manage_crm')->name('crm.quotation.download');
    Route::post('crm/quotations/{quotation}/convert', [Admin\CrmController::class, 'convertQuotation'])->middleware('admin.permission:manage_crm')->name('crm.quotation.convert');

    // WhatsApp Settings (config overview + webhook + connection test)
    Route::get('whatsapp-settings', [Admin\WhatsAppSettingsController::class, 'index'])->middleware('admin.permission:manage_crm')->name('whatsapp-settings.index');
    Route::put('whatsapp-settings', [Admin\WhatsAppSettingsController::class, 'update'])->middleware('admin.permission:manage_crm')->name('whatsapp-settings.update');
    Route::post('whatsapp-settings/test', [Admin\WhatsAppSettingsController::class, 'test'])->middleware('admin.permission:manage_crm')->name('whatsapp-settings.test');

    // WhatsApp Inbox
    Route::get('whatsapp', [Admin\WhatsAppInboxController::class, 'index'])->middleware('admin.permission:manage_crm')->name('whatsapp.index');
    Route::get('whatsapp/conversations-sync', [Admin\WhatsAppInboxController::class, 'conversationsSync'])->middleware('admin.permission:manage_crm')->name('whatsapp.conversations-sync');
    Route::get('whatsapp/{conversation}/messages', [Admin\WhatsAppInboxController::class, 'messages'])->middleware('admin.permission:manage_crm')->name('whatsapp.messages');
    Route::post('whatsapp/{conversation}/reply', [Admin\WhatsAppInboxController::class, 'reply'])->middleware('admin.permission:manage_crm')->name('whatsapp.reply');
    Route::post('whatsapp/{conversation}/template', [Admin\WhatsAppInboxController::class, 'template'])->middleware('admin.permission:manage_crm')->name('whatsapp.template');
    Route::post('whatsapp/{conversation}/document', [Admin\WhatsAppInboxController::class, 'sendDocument'])->middleware('admin.permission:manage_crm')->name('whatsapp.document');
    Route::post('whatsapp/{conversation}/toggle-bot', [Admin\WhatsAppInboxController::class, 'toggleBot'])->middleware('admin.permission:manage_crm')->name('whatsapp.toggle-bot');
    Route::post('whatsapp/{conversation}/trigger-bot-reply', [Admin\WhatsAppInboxController::class, 'triggerBotReply'])->middleware('admin.permission:manage_crm')->name('whatsapp.trigger-bot-reply');
    Route::post('whatsapp/{conversation}/mark-read', [Admin\WhatsAppInboxController::class, 'markRead'])->middleware('admin.permission:manage_crm')->name('whatsapp.mark-read');
    Route::post('whatsapp/{conversation}/assign', [Admin\WhatsAppInboxController::class, 'assign'])->middleware('admin.permission:manage_crm')->name('whatsapp.assign');
    Route::post('whatsapp/{conversation}/notes', [Admin\WhatsAppInboxController::class, 'storeNote'])->middleware('admin.permission:manage_crm')->name('whatsapp.notes.store');
    Route::get('whatsapp/{conversation}/export', [Admin\WhatsAppInboxController::class, 'exportChat'])->middleware('admin.permission:manage_crm')->name('whatsapp.export');
    Route::delete('whatsapp/{conversation}', [Admin\WhatsAppInboxController::class, 'destroy'])->middleware('admin.permission:manage_crm')->name('whatsapp.destroy');

    // WhatsApp Templates (synced from Meta)
    Route::get('whatsapp-templates', [Admin\WhatsAppTemplateController::class, 'index'])->middleware('admin.permission:manage_crm')->name('whatsapp-templates.index');
    Route::get('whatsapp-templates/create', [Admin\WhatsAppTemplateController::class, 'create'])->middleware('admin.permission:manage_crm')->name('whatsapp-templates.create');
    Route::post('whatsapp-templates', [Admin\WhatsAppTemplateController::class, 'store'])->middleware('admin.permission:manage_crm')->name('whatsapp-templates.store');
    Route::post('whatsapp-templates/sync', [Admin\WhatsAppTemplateController::class, 'sync'])->middleware('admin.permission:manage_crm')->name('whatsapp-templates.sync');

    // Contact Groups (targeting)
    Route::get('contact-groups', [Admin\ContactGroupController::class, 'index'])->middleware('admin.permission:manage_crm')->name('contact-groups.index');
    Route::get('contact-groups/create', [Admin\ContactGroupController::class, 'create'])->middleware('admin.permission:manage_crm')->name('contact-groups.create');
    Route::post('contact-groups', [Admin\ContactGroupController::class, 'store'])->middleware('admin.permission:manage_crm')->name('contact-groups.store');
    Route::get('contact-groups/{contactGroup}', [Admin\ContactGroupController::class, 'show'])->middleware('admin.permission:manage_crm')->name('contact-groups.show');
    Route::get('contact-groups/{contactGroup}/edit', [Admin\ContactGroupController::class, 'edit'])->middleware('admin.permission:manage_crm')->name('contact-groups.edit');
    Route::put('contact-groups/{contactGroup}', [Admin\ContactGroupController::class, 'update'])->middleware('admin.permission:manage_crm')->name('contact-groups.update');
    Route::delete('contact-groups/{contactGroup}', [Admin\ContactGroupController::class, 'destroy'])->middleware('admin.permission:manage_crm')->name('contact-groups.destroy');
    Route::post('contact-groups/{contactGroup}/members', [Admin\ContactGroupController::class, 'addMember'])->middleware('admin.permission:manage_crm')->name('contact-groups.members.add');
    Route::delete('contact-groups/{contactGroup}/members/{contact}', [Admin\ContactGroupController::class, 'removeMember'])->middleware('admin.permission:manage_crm')->name('contact-groups.members.remove');

    // WhatsApp Campaigns
    Route::get('whatsapp-campaigns', [Admin\WhatsAppCampaignController::class, 'index'])->middleware('admin.permission:manage_crm')->name('whatsapp-campaigns.index');
    Route::get('whatsapp-campaigns/create', [Admin\WhatsAppCampaignController::class, 'create'])->middleware('admin.permission:manage_crm')->name('whatsapp-campaigns.create');
    Route::post('whatsapp-campaigns', [Admin\WhatsAppCampaignController::class, 'store'])->middleware('admin.permission:manage_crm')->name('whatsapp-campaigns.store');
    Route::get('whatsapp-campaigns/{whatsappCampaign}', [Admin\WhatsAppCampaignController::class, 'show'])->middleware('admin.permission:manage_crm')->name('whatsapp-campaigns.show');
    Route::post('whatsapp-campaigns/{whatsappCampaign}/send', [Admin\WhatsAppCampaignController::class, 'sendNow'])->middleware('admin.permission:manage_crm')->name('whatsapp-campaigns.send');
    Route::post('whatsapp-campaigns/{whatsappCampaign}/schedule', [Admin\WhatsAppCampaignController::class, 'schedule'])->middleware('admin.permission:manage_crm')->name('whatsapp-campaigns.schedule');
    Route::post('whatsapp-campaigns/{whatsappCampaign}/cancel', [Admin\WhatsAppCampaignController::class, 'cancel'])->middleware('admin.permission:manage_crm')->name('whatsapp-campaigns.cancel');
    Route::post('whatsapp-campaigns/{whatsappCampaign}/refresh', [Admin\WhatsAppCampaignController::class, 'refresh'])->middleware('admin.permission:manage_crm')->name('whatsapp-campaigns.refresh');
    Route::post('whatsapp-campaigns/{whatsappCampaign}/retry', [Admin\WhatsAppCampaignController::class, 'retry'])->middleware('admin.permission:manage_crm')->name('whatsapp-campaigns.retry');
    Route::post('whatsapp-campaigns/{whatsappCampaign}/process-now', [Admin\WhatsAppCampaignController::class, 'processPending'])->middleware('admin.permission:manage_crm')->name('whatsapp-campaigns.process-now');

    // WhatsApp Auto-replies (keyword chatbot)
    Route::get('whatsapp-auto-replies', [Admin\WhatsAppAutoReplyController::class, 'index'])->middleware('admin.permission:manage_crm')->name('whatsapp-auto-replies.index');
    Route::post('whatsapp-auto-replies', [Admin\WhatsAppAutoReplyController::class, 'store'])->middleware('admin.permission:manage_crm')->name('whatsapp-auto-replies.store');
    Route::put('whatsapp-auto-replies/{autoReply}', [Admin\WhatsAppAutoReplyController::class, 'update'])->middleware('admin.permission:manage_crm')->name('whatsapp-auto-replies.update');
    Route::post('whatsapp-auto-replies/{autoReply}/toggle', [Admin\WhatsAppAutoReplyController::class, 'toggle'])->middleware('admin.permission:manage_crm')->name('whatsapp-auto-replies.toggle');
    Route::delete('whatsapp-auto-replies/{autoReply}', [Admin\WhatsAppAutoReplyController::class, 'destroy'])->middleware('admin.permission:manage_crm')->name('whatsapp-auto-replies.destroy');

    // CRM Tasks & follow-ups
    Route::get('crm-tasks', [Admin\CrmTaskController::class, 'index'])->middleware('admin.permission:manage_crm')->name('crm-tasks.index');
    Route::post('crm-tasks', [Admin\CrmTaskController::class, 'store'])->middleware('admin.permission:manage_crm')->name('crm-tasks.store');
    Route::post('crm-tasks/{task}/complete', [Admin\CrmTaskController::class, 'complete'])->middleware('admin.permission:manage_crm')->name('crm-tasks.complete');

    // CRM Pipelines (settings — read-only)
    Route::get('crm-pipelines', [Admin\CrmPipelineController::class, 'index'])->middleware('admin.permission:manage_crm_pipelines')->name('crm-pipelines.index');

    // Contacts (Customer 360) — static paths precede the {contact} wildcard.
    Route::get('contacts', [Admin\ContactController::class, 'index'])->middleware('admin.permission:manage_contacts')->name('contacts.index');
    Route::post('contacts', [Admin\ContactController::class, 'store'])->middleware('admin.permission:manage_contacts')->name('contacts.store');
    Route::put('contacts/{contact}', [Admin\ContactController::class, 'update'])->middleware('admin.permission:manage_contacts')->name('contacts.update');
    Route::post('contacts/{contact}/merge', [Admin\ContactController::class, 'merge'])->middleware('admin.permission:manage_contacts')->name('contacts.merge');
    Route::get('contacts/{contact}', [Admin\ContactController::class, 'show'])->middleware('admin.permission:manage_contacts')->name('contacts.show');

    // Affiliate Marketing
    Route::get('affiliates', [Admin\AffiliateController::class, 'index'])->middleware('admin.permission:manage_affiliates')->name('affiliates.index');
    Route::post('affiliates/{affiliate}/status', [Admin\AffiliateController::class, 'updateStatus'])->middleware('admin.permission:manage_affiliates')->name('affiliates.status');
    Route::post('affiliates/commissions/{commission}/pay', [Admin\AffiliateController::class, 'payCommission'])->middleware('admin.permission:manage_affiliates')->name('affiliates.pay');

    // Staff / Roles / Permissions
    Route::resource('staff', Admin\StaffController::class)->except('show')->middleware('admin.permission:manage_staff');
    Route::resource('roles', Admin\RoleController::class)->except('show')->middleware('admin.permission:manage_roles');
    Route::get('permissions', [Admin\RoleController::class, 'permissions'])->middleware('admin.permission:manage_permissions')->name('permissions.index');

    // Suppliers & pricing
    Route::resource('suppliers', Admin\SupplierController::class)->except('show')->middleware('admin.permission:manage_suppliers');
    Route::post('suppliers/{supplier}/credentials', [Admin\SupplierController::class, 'saveCredentials'])->middleware('admin.permission:manage_suppliers')->name('suppliers.credentials');
    Route::resource('pricing-rules', Admin\PricingRuleController::class)->except('show')->middleware('admin.permission:manage_pricing');
    Route::resource('taxes', Admin\TaxController::class)->except('show')->middleware('admin.permission:manage_pricing');

    // Catalog CRUDs
    Route::resource('destinations', Admin\DestinationController::class)->except('show')->middleware('admin.permission:manage_content');
    Route::resource('packages', Admin\PackageController::class)->except('show')->middleware('admin.permission:manage_packages');
    Route::get('packages-export', [Admin\PackageController::class, 'export'])->middleware('admin.permission:manage_packages')->name('packages.export');
    Route::post('packages/{package}/itineraries', [Admin\PackageController::class, 'storeItinerary'])->middleware('admin.permission:manage_packages')->name('packages.itineraries');
    Route::put('packages/{package}/itineraries/{itinerary}', [Admin\PackageController::class, 'updateItinerary'])->middleware('admin.permission:manage_packages')->name('packages.itineraries.update');
    Route::delete('packages/{package}/itineraries/{itinerary}', [Admin\PackageController::class, 'destroyItinerary'])->middleware('admin.permission:manage_packages')->name('packages.itineraries.destroy');
    Route::post('packages/{package}/prices', [Admin\PackageController::class, 'storePrice'])->middleware('admin.permission:manage_packages')->name('packages.prices');
    Route::post('packages/{package}/departures', [Admin\PackageController::class, 'storeDeparture'])->middleware('admin.permission:manage_packages')->name('packages.departures');
    // Package hotel selection config (segments + options)
    Route::post('packages/{package}/hotel-segments', [Admin\PackageHotelController::class, 'storeSegment'])->middleware('admin.permission:manage_packages')->name('packages.hotel-segments.store');
    Route::put('packages/{package}/hotel-segments/{segment}', [Admin\PackageHotelController::class, 'updateSegment'])->middleware('admin.permission:manage_packages')->name('packages.hotel-segments.update');
    Route::delete('packages/{package}/hotel-segments/{segment}', [Admin\PackageHotelController::class, 'destroySegment'])->middleware('admin.permission:manage_packages')->name('packages.hotel-segments.destroy');
    Route::post('packages/{package}/hotel-options', [Admin\PackageHotelController::class, 'storeOption'])->middleware('admin.permission:manage_packages')->name('packages.hotel-options.store');
    Route::put('packages/{package}/hotel-options/{option}', [Admin\PackageHotelController::class, 'updateOption'])->middleware('admin.permission:manage_packages')->name('packages.hotel-options.update');
    Route::delete('packages/{package}/hotel-options/{option}', [Admin\PackageHotelController::class, 'destroyOption'])->middleware('admin.permission:manage_packages')->name('packages.hotel-options.destroy');
    // Package flight options (MakeMyTrip-style with/without flights)
    Route::post('packages/{package}/flight-options', [Admin\PackageFlightController::class, 'storeOption'])->middleware('admin.permission:manage_packages')->name('packages.flight-options.store');
    Route::put('packages/{package}/flight-options/{option}', [Admin\PackageFlightController::class, 'updateOption'])->middleware('admin.permission:manage_packages')->name('packages.flight-options.update');
    Route::delete('packages/{package}/flight-options/{option}', [Admin\PackageFlightController::class, 'destroyOption'])->middleware('admin.permission:manage_packages')->name('packages.flight-options.destroy');
    Route::resource('hotels', Admin\HotelController::class)->except('show')->middleware('admin.permission:manage_hotels');
    Route::get('hotels-export', [Admin\HotelController::class, 'export'])->middleware('admin.permission:manage_hotels')->name('hotels.export');
    Route::resource('hotels.rooms', Admin\HotelRoomController::class)->except('show')->middleware('admin.permission:manage_hotels');
    Route::resource('vehicles', Admin\VehicleController::class)->except('show')->middleware('admin.permission:manage_cabs');
    Route::get('vehicles-export', [Admin\VehicleController::class, 'export'])->middleware('admin.permission:manage_cabs')->name('vehicles.export');
    Route::resource('vehicle-types', Admin\VehicleTypeController::class)->except('show')->middleware('admin.permission:manage_cabs');
    Route::get('vehicle-types-export', [Admin\VehicleTypeController::class, 'export'])->middleware('admin.permission:manage_cabs')->name('vehicle-types.export');
    Route::resource('vendors', Admin\VendorController::class)->except('show')->middleware('admin.permission:manage_cabs');
    Route::get('vendors-export', [Admin\VendorController::class, 'export'])->middleware('admin.permission:manage_cabs')->name('vendors.export');
    Route::resource('cab-locations', Admin\CabLocationController::class)->except('show')->middleware('admin.permission:manage_cabs');
    Route::get('cab-locations-export', [Admin\CabLocationController::class, 'export'])->middleware('admin.permission:manage_cabs')->name('cab-locations.export');
    Route::resource('airports', Admin\AirportController::class)->except('show')->middleware('admin.permission:manage_flights');
    Route::get('airports-export', [Admin\AirportController::class, 'export'])->middleware('admin.permission:manage_flights')->name('airports.export');
    Route::resource('airlines', Admin\AirlineController::class)->except('show')->middleware('admin.permission:manage_flights');
    Route::get('airlines-export', [Admin\AirlineController::class, 'export'])->middleware('admin.permission:manage_flights')->name('airlines.export');

    // Coupons & offers
    Route::resource('coupons', Admin\CouponController::class)->except('show')->middleware('admin.permission:manage_pricing');
    Route::get('coupons-export', [Admin\CouponController::class, 'export'])->middleware('admin.permission:manage_pricing')->name('coupons.export');
    Route::resource('offers', Admin\OfferController::class)->except('show')->middleware('admin.permission:manage_content');
    Route::get('offers-export', [Admin\OfferController::class, 'export'])->middleware('admin.permission:manage_content')->name('offers.export');

    // CMS
    Route::get('homepage', [Admin\HomepageController::class, 'index'])->middleware('admin.permission:manage_content')->name('homepage.index');
    Route::put('homepage/{section}', [Admin\HomepageController::class, 'update'])->middleware('admin.permission:manage_content')->name('homepage.update');
    Route::post('homepage/{section}/toggle', [Admin\HomepageController::class, 'toggle'])->middleware('admin.permission:manage_content')->name('homepage.toggle');
    Route::post('homepage/reorder', [Admin\HomepageController::class, 'reorder'])->middleware('admin.permission:manage_content')->name('homepage.reorder');

    Route::resource('pages', Admin\PageController::class)->except('show')->middleware('admin.permission:manage_content');
    Route::get('pages-export', [Admin\PageController::class, 'export'])->middleware('admin.permission:manage_content')->name('pages.export');
    Route::resource('banners', Admin\BannerController::class)->except('show')->middleware('admin.permission:manage_content');
    Route::get('banners-export', [Admin\BannerController::class, 'export'])->middleware('admin.permission:manage_content')->name('banners.export');
    Route::resource('faqs', Admin\FaqController::class)->except('show')->middleware('admin.permission:manage_content');
    Route::get('faqs-export', [Admin\FaqController::class, 'export'])->middleware('admin.permission:manage_content')->name('faqs.export');
    Route::resource('testimonials', Admin\TestimonialController::class)->except('show')->middleware('admin.permission:manage_content');
    Route::get('testimonials-export', [Admin\TestimonialController::class, 'export'])->middleware('admin.permission:manage_content')->name('testimonials.export');
    Route::resource('blogs', Admin\BlogController::class)->except('show')->middleware('admin.permission:manage_content');
    Route::get('blogs-export', [Admin\BlogController::class, 'export'])->middleware('admin.permission:manage_content')->name('blogs.export');
    Route::resource('guides', Admin\GuideController::class)->except('show')->middleware('admin.permission:manage_content');
    Route::get('guides-export', [Admin\GuideController::class, 'export'])->middleware('admin.permission:manage_content')->name('guides.export');
    Route::resource('menus', Admin\MenuController::class)->except('show')->middleware('admin.permission:manage_content');
    Route::post('menus/{menu}/items', [Admin\MenuController::class, 'storeItem'])->middleware('admin.permission:manage_content')->name('menus.items');
    Route::put('menu-items/{item}', [Admin\MenuController::class, 'updateItem'])->middleware('admin.permission:manage_content')->name('menu-items.update');
    Route::delete('menu-items/{item}', [Admin\MenuController::class, 'destroyItem'])->middleware('admin.permission:manage_content')->name('menu-items.destroy');

    // Reviews
    Route::get('reviews', [Admin\ReviewController::class, 'index'])->middleware('admin.permission:manage_content')->name('reviews.index');
    Route::get('reviews-export', [Admin\ReviewController::class, 'export'])->middleware('admin.permission:manage_content')->name('reviews.export');
    Route::post('reviews/{review}/status', [Admin\ReviewController::class, 'updateStatus'])->middleware('admin.permission:manage_content')->name('reviews.status');
    Route::post('reviews/{review}/feature', [Admin\ReviewController::class, 'feature'])->middleware('admin.permission:manage_content')->name('reviews.feature');

    // Media library
    Route::get('media', [Admin\MediaController::class, 'index'])->middleware('admin.permission:manage_content')->name('media.index');
    Route::post('media', [Admin\MediaController::class, 'store'])->middleware('admin.permission:manage_content')->name('media.store');
    Route::post('media/upload', [Admin\MediaController::class, 'upload'])->name('media.upload');
    Route::delete('media/{media}', [Admin\MediaController::class, 'destroy'])->middleware('admin.permission:manage_content')->name('media.destroy');

    // SEO
    Route::get('seo', [Admin\SeoController::class, 'index'])->middleware('admin.permission:manage_settings')->name('seo.index');
    Route::put('seo', [Admin\SeoController::class, 'update'])->middleware('admin.permission:manage_settings')->name('seo.update');

    // Support & messages
    Route::get('tickets', [Admin\SupportController::class, 'index'])->middleware('admin.permission:manage_content')->name('tickets.index');
    Route::get('tickets/{ticket}', [Admin\SupportController::class, 'show'])->middleware('admin.permission:manage_content')->name('tickets.show');
    Route::post('tickets/{ticket}/reply', [Admin\SupportController::class, 'reply'])->middleware('admin.permission:manage_content')->name('tickets.reply');
    Route::post('tickets/{ticket}/status', [Admin\SupportController::class, 'updateStatus'])->middleware('admin.permission:manage_content')->name('tickets.status');
    Route::get('messages', [Admin\SupportController::class, 'messages'])->middleware('admin.permission:manage_content')->name('messages.index');

    // Notification templates
    Route::get('notification-templates', [Admin\NotificationTemplateController::class, 'index'])->middleware('admin.permission:manage_settings')->name('notification-templates.index');
    Route::put('notification-templates/{template}', [Admin\NotificationTemplateController::class, 'update'])->middleware('admin.permission:manage_settings')->name('notification-templates.update');

    // Payment gateways
    Route::get('gateways', [Admin\GatewayController::class, 'index'])->middleware('admin.permission:manage_settings')->name('gateways.index');
    Route::put('gateways/{gateway}', [Admin\GatewayController::class, 'update'])->middleware('admin.permission:manage_settings')->name('gateways.update');

    // Reports
    Route::get('reports', [Admin\ReportController::class, 'index'])->middleware('admin.permission:manage_reports')->name('reports.index');
    Route::get('reports/export', [Admin\ReportController::class, 'export'])->middleware('admin.permission:manage_reports')->name('reports.export');
    Route::get('reports/financial-export', [Admin\ReportController::class, 'exportFinancial'])->middleware('admin.permission:manage_reports')->name('reports.financial-export');

    // Website analytics dashboard
    Route::get('analytics', [Admin\AnalyticsController::class, 'index'])->middleware('admin.permission:view_analytics')->name('analytics.index');
    Route::get('analytics/realtime', [Admin\AnalyticsController::class, 'realtime'])->middleware('admin.permission:view_analytics')->name('analytics.realtime');

    // Settings
    Route::get('integrations', [Admin\IntegrationsController::class, 'index'])->middleware('admin.permission:manage_settings')->name('integrations.index');

    Route::get('ai-settings', [Admin\AiSettingsController::class, 'index'])->middleware('admin.permission:manage_settings')->name('ai-settings.index');
    Route::put('ai-settings', [Admin\AiSettingsController::class, 'update'])->middleware('admin.permission:manage_settings')->name('ai-settings.update');
    Route::post('ai-settings/test', [Admin\AiSettingsController::class, 'test'])->middleware('admin.permission:manage_settings')->name('ai-settings.test');

    Route::get('sms-settings', [Admin\SmsSettingsController::class, 'index'])->middleware('admin.permission:manage_settings')->name('sms-settings.index');
    Route::put('sms-settings', [Admin\SmsSettingsController::class, 'update'])->middleware('admin.permission:manage_settings')->name('sms-settings.update');
    Route::post('sms-settings/test', [Admin\SmsSettingsController::class, 'test'])->middleware('admin.permission:manage_settings')->name('sms-settings.test');

    Route::get('social-login-settings', [Admin\SocialLoginSettingsController::class, 'index'])->middleware('admin.permission:manage_settings')->name('social-login-settings.index');
    Route::put('social-login-settings', [Admin\SocialLoginSettingsController::class, 'update'])->middleware('admin.permission:manage_settings')->name('social-login-settings.update');

    Route::get('settings', [Admin\SettingsController::class, 'index'])->middleware('admin.permission:manage_settings')->name('settings.index');
    Route::put('settings', [Admin\SettingsController::class, 'update'])->middleware('admin.permission:manage_settings')->name('settings.update');
    Route::post('settings/test-email', [Admin\SettingsController::class, 'sendTestEmail'])->middleware('admin.permission:manage_settings')->name('settings.test-email');

    // Activity log
    Route::get('activity-logs', [Admin\ActivityLogController::class, 'index'])->middleware('admin.permission:manage_settings')->name('activity.index');

    /*
    | Trip Operations & Arrival Management — operational overlay on confirmed
    | bookings. Reads/edits never duplicate booking data; they reference it.
    */
    Route::prefix('trip-ops')->name('trip-ops.')->group(function () {
        // Dashboards & queues (read)
        Route::get('/', [Admin\TripOperationsController::class, 'today'])->middleware('admin.permission:view_trip_operations')->name('today');
        Route::get('upcoming', [Admin\TripOperationsController::class, 'upcoming'])->middleware('admin.permission:view_trip_operations')->name('upcoming');
        Route::get('active', [Admin\TripOperationsController::class, 'active'])->middleware('admin.permission:view_trip_operations')->name('active');
        Route::get('completed', [Admin\TripOperationsController::class, 'completed'])->middleware('admin.permission:view_trip_operations')->name('completed');
        Route::get('export', [Admin\TripOperationsController::class, 'export'])->middleware('admin.permission:view_trip_operations')->name('export');

        // Driver directory (static before {trip} wildcards)
        Route::get('drivers', [Admin\DriverController::class, 'index'])->middleware('admin.permission:manage_drivers')->name('drivers.index');
        Route::post('drivers', [Admin\DriverController::class, 'store'])->middleware('admin.permission:manage_drivers')->name('drivers.store');
        Route::put('drivers/{driver}', [Admin\DriverController::class, 'update'])->middleware('admin.permission:manage_drivers')->name('drivers.update');
        Route::post('drivers/{driver}/toggle', [Admin\DriverController::class, 'toggle'])->middleware('admin.permission:manage_drivers')->name('drivers.toggle');
        Route::delete('drivers/{driver}', [Admin\DriverController::class, 'destroy'])->middleware('admin.permission:manage_drivers')->name('drivers.destroy');

        // Assignments (global queue + reassign/cancel by assignment)
        Route::get('assignments', [Admin\TripAssignmentController::class, 'index'])->middleware('admin.permission:view_trip_operations')->name('assignments.index');
        Route::post('assignments/{assignment}/reassign', [Admin\TripAssignmentController::class, 'reassign'])->middleware('admin.permission:assign_drivers')->name('assignments.reassign');
        Route::post('assignments/{assignment}/cancel', [Admin\TripAssignmentController::class, 'cancel'])->middleware('admin.permission:assign_drivers')->name('assignments.cancel');

        // Settings
        Route::get('settings', [Admin\TripOperationsController::class, 'settings'])->middleware('admin.permission:manage_trip_operations')->name('settings');
        Route::put('settings', [Admin\TripOperationsController::class, 'updateSettings'])->middleware('admin.permission:manage_trip_operations')->name('settings.update');

        // Per-trip: timeline, PDFs, comms, assignment (static suffixes before {trip})
        Route::get('{trip}', [Admin\TripOperationsController::class, 'show'])->middleware('admin.permission:view_trip_operations')->name('show');
        Route::get('{trip}/driver-sheet', [Admin\TripOperationsController::class, 'driverSheet'])->middleware('admin.permission:view_trip_operations')->name('driver-sheet');
        Route::get('{trip}/itinerary', [Admin\TripOperationsController::class, 'customerItineraryPdf'])->middleware('admin.permission:view_trip_operations')->name('itinerary');
        Route::post('{trip}/regenerate', [Admin\TripOperationsController::class, 'regenerate'])->middleware('admin.permission:manage_trip_operations')->name('regenerate');
        Route::post('{trip}/notes', [Admin\TripOperationsController::class, 'updateNotes'])->middleware('admin.permission:manage_trip_operations')->name('notes');
        Route::post('{trip}/status', [Admin\TripOperationsController::class, 'updateStatus'])->middleware('admin.permission:manage_trip_operations')->name('status');
        Route::post('{trip}/send-itinerary', [Admin\TripOperationsController::class, 'sendItinerary'])->middleware('admin.permission:send_trip_communications')->name('send-itinerary');
        Route::post('{trip}/send-driver-reminder', [Admin\TripOperationsController::class, 'sendDriverReminder'])->middleware('admin.permission:send_trip_communications')->name('send-driver-reminder');
        Route::post('{trip}/send-driver-sheet', [Admin\TripOperationsController::class, 'sendDriverSheet'])->middleware('admin.permission:send_trip_communications')->name('send-driver-sheet');
        Route::post('{trip}/send-tomorrow-plan', [Admin\TripOperationsController::class, 'sendTomorrowPlan'])->middleware('admin.permission:send_trip_communications')->name('send-tomorrow-plan');
        Route::post('{trip}/assign', [Admin\TripAssignmentController::class, 'store'])->middleware('admin.permission:assign_drivers')->name('assign');
    });
});
