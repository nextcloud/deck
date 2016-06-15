app.service('StatusService', function(){
    // Status Helper
    this.active = true;
    this.icon = 'loading';
    this.title = 'Please wait';
    this.text = 'Es dauert noch einen kleinen Moment';
    this.counter = 2;

    this.setStatus = function($icon, $title, $text) {
        this.active = true;
        this.icon = $icon;
        this.title = $title;
        this.text = $text;
    }

    this.setError = function($title, $text) {
        this.active = true;
        this.icon = 'error';
        this.title = $title;
        this.text = $text;
        this.counter = 0;
    }

    this.releaseWaiting = function() {
        if(this.counter>0)
            this.counter--;
        if(this.counter<=0) {
            this.active = false;
            this.counter = 0;
        }
    }

    this.unsetStatus = function() {
        this.active = false;
    }

});

