<?php

namespace App\Cms\Hooks;

final class HookPoints
{
    public const CMS_BOOT = 'cms.boot';
    public const CMS_BOOTED = 'cms.booted';
    public const CMS_ROUTES = 'cms.routes';
    public const CMS_ADMIN_MENU = 'cms.admin.menu';
    public const CMS_ENQUEUE_ASSETS = 'cms.enqueue_assets';
    public const CMS_ENQUEUE_ASSETS_ADMIN = 'cms.enqueue_assets_admin';
    public const CMS_REGISTER_MENUS = 'cms.register_menus';
    public const CMS_REGISTER_SIDEBARS = 'cms.register_sidebars';
    public const CMS_REGISTER_WIDGETS = 'cms.register_widgets';
    public const CMS_REGISTER_BLOCKS = 'cms.register_blocks';
    public const CMS_THE_CONTENT = 'cms.the_content';
    public const CMS_SEO_META = 'cms.seo.meta';
    public const FILAMENT_ADMIN_PANEL = 'filament.admin.panel';

}