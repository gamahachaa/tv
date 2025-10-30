/*
 treeMenu - jQuery plugin
 version: 0.6

 Copyright 2014 Stepan Krapivin

*/
(function($){
    $.fn.treemenu = function(options) {
        options = options || {};
        options.delay = options.delay || 0;
        options.openActive = options.openActive || false;
        options.closeOther = options.closeOther || false;
        options.activeSelector = options.activeSelector || ".active";

        this.addClass("treemenu");

        if (!options.nonroot) {
            this.addClass("treemenu-root");
        }
        var onclicked = function() {
            var li = $(this).parent('li');
            // Only toggle tree states if this is NOT a leaf node (tree-empty)
            if (!li.hasClass('tree-empty')) {
                if (options.closeOther && li.hasClass('tree-closed')) {
                    var siblings = li.parent('ul').find("li:not(.tree-empty)");
                    siblings.removeClass("tree-opened");
                    siblings.addClass("tree-closed");
                    siblings.removeClass(options.activeSelector);
                    siblings.find('> ul').slideUp(options.delay);
                }

                li.find('> ul').slideToggle(options.delay);
                li.toggleClass('tree-opened');
                li.toggleClass('tree-closed');
                li.toggleClass(options.activeSelector);
            }
        }
        options.nonroot = true;

        this.find("> li").each(function() {
            e = $(this);
            var subtree = e.find('> ul');
            var button = e.find('.toggler').eq(0);
            var anchor = e.find('a');

            if(button.length == 0) {
                // create toggler
                var button = $('<span>');
                button.addClass('toggler');
                e.prepend(button);
            }

            if(subtree.length > 0) {
                subtree.hide();

                e.addClass('tree-closed');
                e.find(button).click(onclicked);
                e.find(anchor).click(onclicked);

                $(this).find('> ul').treemenu(options);
            } else {
                $(this).addClass('tree-empty');
            }
        });

        if (options.openActive) {
            var cls = this.attr("class");

            this.find(options.activeSelector).each(function(){
                var el = $(this).parent();

                while (el.attr("class") !== cls) {
                    el.find('> ul').show();
                    if(el.prop("tagName") === 'UL') {
                        el.show();
                    } else if (el.prop("tagName") === 'LI') {
                        el.removeClass('tree-closed');
                        if (!el.hasClass('tree-empty')) {
                            el.addClass("tree-opened");
                        }
                        el.show();
                    }

                    el = el.parent();
                }
            });
        }

        return this;
    }
})(jQuery);
