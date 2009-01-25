// JavaScript Document

Ext.app.PlayerWindow = Ext.extend(Ext.app.Module, {
    id:'player-win',
    init : function(){
        this.launcher = {
            text: 'Player',
            iconCls:'player',
            handler : this.createWindow,
            scope: this
        }
    },
	
    createWindow : function(){
        var desktop = this.app.getDesktop();
		
		
		var main = new Ext.ux.MediaPanel({
			region: 'center',
			mediaCfg: {
				mediaType: 'JWP',
				url: template_path + 'player.swf',
				params: {
					'flashvars' : 'file=http://dev.bjcullinan.com/plugins/passthru/file.wmv'
					//'autostart' : true
				}
			}
		});
		
		var win = desktop.createWindow({
			title: 'Player Window',
			width: 700,
			height: 450,
			iconCls: 'player',
			shim: false,
			animCollapse: false,
			constrainHeader: true,
			cls: 'player-window',
			layout: 'border',
			border: false,
			items: [main]
		});
		

        win.show();
	}
	
});