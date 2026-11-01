// What the app runs when it starts, in the official app and in Aty alike: a notice's link opens the book's page or the library
// inside the app, instead of the list of notifications. The only JavaScript of this plugin in the app.
const that = this;

class AyvurendaLinks extends that.CoreContentLinksHandlerBase {
    constructor() {
        super();
        this.name = 'local_oksigeniaayvurenda_links';
        this.pattern = /\/local\/oksigeniaayvurenda\/(record|index)\.php/;
    }

    getActions(siteIds, url, params) {
        const record = url.includes('/record.php');
        const method = record ? 'mobile_record_view' : 'mobile_library_view';
        const args = record ? {library: params.library, id: parseInt(params.id, 10)} : {};
        return [{
            action: (siteId) => that.CoreNavigatorService.navigateToSitePath(
                'siteplugins/content/local_oksigeniaayvurenda/' + method + '/' + that.Md5.hashAsciiStr(JSON.stringify(args)),
                {
                    params: {title: that.TranslateService.instant('plugin.local_oksigeniaayvurenda.mobilelibrary'), args},
                    siteId,
                }
            ),
        }];
    }
}

that.CoreContentLinksDelegate.registerHandler(new AyvurendaLinks());
