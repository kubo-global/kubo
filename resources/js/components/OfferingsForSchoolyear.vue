<template>
    <div>
            <div v-for="grade in grades">
                <h4>{{ grade.name }} </h4>
                <offerings-for-grade :grade_id="grade.id" :schoolyear_id="schoolyear_id"></offerings-for-grade>
                <hr>
            </div>
    </div>
</template>

<script>

    import OfferingsForGrade from './OfferingsForGrade.vue';

    export default {
        props: ['schoolyear_id'],

        data () {
            return {
                grades: []
            }
        },

        components: {OfferingsForGrade},

        created () {
            axios
                .get('/api/grades', {
                    schoolyear_id: this.schoolyear_id
                })
                .then((response) => {
                    this.grades = response.data;
                })
                .catch( (error) => {
                    console.log(error);
                });
        },

        mounted() {
            console.log('Component mounted.');
        }
    }

</script>
