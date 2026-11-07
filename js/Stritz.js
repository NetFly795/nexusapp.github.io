var king = king || {};
king.stritz = function() {
    var pauseElement;
    var hasListeners;
    var hasTextImage;
    var hasToBeHidden;

    var showClient = function(event) {
        if (hasListeners) {
            _showClient();
        }

        hasToBeHidden = false;
    };

    var forceToShowClient = function (event) {
        _showClient();

        if (hasToBeHidden) {
            hideClient(event);
        }
    };

    var _showClient = function () {
        $('#' + king.gameId).css({'top':0});

        var game = king.game();
        if (game.stateResume) {
            game.stateResume();
        }

        pauseElement.hide();

        if (hasTextImage) {
            removeTextImage();
        }

        if (!hasListeners) {
            addListeners();
        }
    };

    var hideClient = function(event) {
        if (!hasListeners) {
            hasToBeHidden = true;

        } else {
            var game = king.game();
            if (game.statePause) {
                game.statePause();
            }

            hasToBeHidden = false;
        }
    };

    var hideClientForProfileCard = function(event) {
        if (hasListeners) {
            removeListeners();
        }

        var game = king.game();
        if (game.statePauseForProfileCard) {
            game.statePauseForProfileCard();
        }

        hasToBeHidden = false;
    };

    var addListeners = function() {
        king.eventBus.addListener(king.EventType.SHOW_CLIENT, showClient);
        king.eventBus.addListener(king.EventType.HIDE_CLIENT, hideClient);
        hasListeners = true;
    };

    var removeListeners = function() {
        king.eventBus.removeListener(king.EventType.SHOW_CLIENT, showClient);
        king.eventBus.removeListener(king.EventType.HIDE_CLIENT, hideClient);
        hasListeners = false;
    };

    var init = function() {
        addListeners();
        pauseElement = $('#' + king.pauseId);
    };

    var setPauseImage = function(data, width, height) {
        pauseElement.show();
        $('#' + king.gameId).css("top", -10000);

        pauseElement.find(".image").attr({
            'src' : 'data:image/jpeg;base64,' + data,
            'width': width + 'px',
            'height': height + 'px'
        });
    };

    var removeTextImage = function() {
        pauseElement.find(".text").attr({
            'src' : '',
            'width': '0px',
            'height': '0px'
        });
        hasTextImage = false;
    };

    var setTextImage = function(data, x, y, width, height) {
        pauseElement.find(".text").attr({
            'src' : 'data:image/png;base64,' + data,
            'width': width + 'px',
            'height': height + 'px'
        });
        pauseElement.find(".text").css({
            'top': y + "px",
            'left': x + "px"
        });
        hasTextImage = true;
    };

    return {
        init : init,
        showClient : showClient,
        hideClient : hideClient,
        forceToShowClient : forceToShowClient,
        hideClientForProfileCard : hideClientForProfileCard,
        setPauseImage : setPauseImage,
        setTextImage : setTextImage
    };
}();
