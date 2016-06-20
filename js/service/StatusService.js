app.factory('StatusService', function(){
    // Status Helper
    var StatusService = function() {
        this.active = true;
        this.icon = 'loading';
        this.title = 'Please wait';
        this.text = 'Es dauert noch einen kleinen Moment';
        this.counter = 0;
    }


    StatusService.prototype.setStatus = function($icon, $title, $text) {
        this.active = true;
        this.icon = $icon;
        this.title = $title;
        this.text = $text;
    }

    StatusService.prototype.setError = function($title, $text) {
        this.active = true;
        this.icon = 'error';
        this.title = $title;
        this.text = $text;
        this.counter = 0;
    }

    StatusService.prototype.releaseWaiting = function() {
        if(this.counter>0)
            this.counter--;
        if(this.counter<=0) {
            this.active = false;
            this.counter = 0;
        }
    }

    StatusService.prototype.retainWaiting = function() {
        this.active = true;
        this.icon = 'loading';
        this.title = 'Please wait';
        this.text = 'Es dauert noch einen kleinen Moment';
        this.counter++;
    }

    StatusService.prototype.unsetStatus = function() {
        this.active = false;
    }

    return {
        getInstance: function() {
            return new StatusService();
        }
    }

});


