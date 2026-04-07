(function(){
    'use strict';

    function showMoreChips(target){
        var selector = target === 'cats'
            ? '.mu-navchips-list--cats .mu-navchips-chip--cat-hidden'
            : '.mu-navchips-list--tags .mu-navchips-chip--tag-hidden';

        document.querySelectorAll(selector).forEach(function(el){
            el.style.display = 'inline-flex';
        });

        var btnSelector = target === 'cats'
            ? '.mu-navchips-list--cats .mu-navchips-chip--more'
            : '.mu-navchips-list--tags .mu-navchips-chip--more-tags';

        var btn = document.querySelector(btnSelector);
        if(btn){
            btn.style.display = 'none';
        }
    }

    function initNavChips(){
        var moreButtons = document.querySelectorAll('.mu-navchips-more-btn');
        if(!moreButtons.length) return;

        moreButtons.forEach(function(btn){
            if(btn.dataset.muNavchipsBound === '1') return;
            btn.dataset.muNavchipsBound = '1';

            btn.addEventListener('click', function(e){
                e.preventDefault();
                var target = btn.getAttribute('data-target');
                if(!target) return;
                showMoreChips(target);
            });
        });
    }

    function onReady(fn){
        if(document.readyState === 'loading'){
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(initNavChips);
})();
