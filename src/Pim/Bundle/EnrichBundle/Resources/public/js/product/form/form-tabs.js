'use strict';

define(
    [
        'underscore',
        'backbone',
        'pim/form',
        'text!pim/template/product/form-tabs'
    ],
    function (_, Backbone, BaseForm, template) {
        return BaseForm.extend({
            template: _.template(template),
            className: 'tabbable tabs-top',
            events: {
                'click header ul.nav-tabs li': 'changeTab'
            },
            initialize: function () {
                this.state = new Backbone.Model();

                this.listenTo(this.state, 'change', this.render);

                BaseForm.prototype.initialize.apply(this, arguments);
            },
            configure: function () {
                this.getRoot().addTab = _.bind(this.addTab, this);

                return BaseForm.prototype.configure.apply(this, arguments);
            },
            addTab: function (code, label) {
                var tabs = this.state.get('tabs') || [];
                tabs.push({ code: code, label: label });

                this.state.set('tabs', tabs, {silent: true});

                if (this.state.get('currentTab') === undefined) {
                    this.state.set('currentTab', tabs[0].code, {silent: true});
                }

                this.state.trigger('change');
            },
            render: function () {
                if (!this.configured) {
                    return this;
                }

                this.$el.html(
                    this.template({
                        state: this.state.toJSON()
                    })
                );
                this.delegateEvents();

                var currentTab = this.extensions[this.state.get('currentTab')];
                currentTab.getTargetElement()[currentTab.insertAction](currentTab.el);
                /* global console */
                console.log(currentTab.parent.code, 'triggered the rendering of', currentTab.code);
                currentTab.render();

                var panels = this.extensions.panels;
                panels.getTargetElement()[panels.insertAction](panels.el);
                panels.render();

                return this;
            },
            changeTab: function (event) {
                this.state.set('currentTab', event.currentTarget.dataset.tab);
                this.state.set('fullPanel', false);
            }
        });
    }
);