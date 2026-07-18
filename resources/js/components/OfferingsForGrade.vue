<template>
<div>
    <offering v-for="(offering, theindex) in offerings" :offering="offering" :key="offering.id" :theindex="theindex" v-on:destroyClass="deleteOffering"></offering>
    <offering :offering="new_offering" v-on:add-offering="addOffering"></offering>
</div>
</template>

<script>

    import Offering from './Offering.vue';

    export default {

        props: ['grade_id', 'schoolyear_id'],
        data () {
            return {
                new_offering: {
                    id: null,
                    name: '+',
                    activated: true
                },
                offering_id: null,
                offerings: []
            }
        },
        components: {Offering},

        methods:  {
            addOffering() {

                let new_offering = {};

                new_offering.grade_id = this.grade_id;
                new_offering.schoolyear_id = this.schoolyear_id;
                new_offering.activated = true;
                new_offering.name = prompt('What\'s the name of the new class?');

                axios
                    .post('/api/offerings', new_offering)
                    .then( (response) => {
                        this.offerings.push(response.data);
                    })
                    .catch(function (error) {
                        console.log(error);
                    });
            },

            deleteOffering(index) {
                    console.log('index ' + index);
                    this.offerings.splice(index, 1);
            }
        },
        created() {

            axios
                .get('/api/offerings', {
                    params: {
                        schoolyear_id: this.schoolyear_id,
                        grade_id: this.grade_id
                    }

                })
                .then((response) => {
                    this.offerings = response.data
                })
                .catch((error) => {
                    console.log(error);
                });
        },


        mounted() {
            console.log('Component mounted.');
        }
    }
</script>
