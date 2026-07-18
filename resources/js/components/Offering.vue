<template>
    <button class='btn' v-bind:class="{ 'btn-primary': this.isActive, 'btn-secondary disabled': !this.isActive }" v-on:click.self="toggle">{{ this.offering.name }} <a href="#" v-on:click="destroy" v-if="!addOffering"><span class="badge">x</span></a></button>
</template>

<style scoped>
    .btn {
        margin-right: 0.5em;
    }
</style>

<script>
    export default {
        props: ['offering', 'theindex'],
        data() {
            return {
                addOffering: false,
                offerings: null,
                isActive: false
            }
        },
        components: {},

        methods:  {
            toggle () {

                if (this.offering.id == null) {
                    console.log('emit');
                    this.$emit('add-offering');

                } else {
                    var enable_or_disable_string = '';
                    this.isActive ? enable_or_disable_string = "disable" : enable_or_disable_string = "enable";

                    if(confirm('Do you want to '+ enable_or_disable_string + ' the offering of '+ this.offering.name + '?')) {
                        axios
                            .post('/api/offerings/toggle', {
                                offering_id: this.offering.id
                            })
                            .then( (response) => {
                                this.isActive = response.data.activated;
                            })
                            .catch(function (error) {
                                console.log(error);
                            });
                    }
                }
            },

            destroy () {

                if (confirm("Are you sure that you want to delete " + this.offering.name + "?")) {
                    axios
                        .delete('/api/offerings/'+this.offering.id)
                        .then( (response) => {
                            this.$emit('destroyClass', this.theindex);
                        })
                        .catch(function (error) {
                            console.log(error);
                        });
                }
            }
        },

        created() {
            if (this.offering.id == null) {
                this.addOffering = true;
            }
        },
        mounted() {
            this.isActive = !!+this.offering.activated;
        }
    }
</script>
