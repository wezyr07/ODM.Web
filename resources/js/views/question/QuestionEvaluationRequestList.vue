<!--
  -  Bu yazılım Elektrik Elektronik Teknolojileri Alanı/Elektrik Öğretmeni Hakan GÜLEN tarafından geliştirilmiş olup geliştirilen bütün kaynak kodlar
  -   Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0) ile lisanslanmıştır.
  -  Ayrıntılı lisans bilgisi için https://creativecommons.org/licenses/by-nc-sa/4.0/legalcode.tr sayfasını ziyaret edebilirsiniz. 2019
  -->

<template>
  <section class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="box">
          <div class="box-header with-border">
            <h4>Soru Değerlendirme İstekleri</h4>
          </div>
          <div class="box-body">
            <h4>Listeleme Parametreleri</h4>
            <div class="row">
              <div class="col-md-12 col-xs-12">
                <div
                  v-if="checkPermission"
                  class="col-md-3 col-xs-12"
                >
                  <div
                    class="form-group has-feedback"
                  >
                    <label>Ders/Alan Seçimi</label>
                    <v-select
                      v-model="selectedBranch"
                      :options="branches"
                      :reduce="b => b.id"
                      label="name"
                      placeholder="Alan/Ders seçebilirsiniz"
                      @input="onSelectionChanged"
                    />
                  </div>
                </div>
              </div>
            </div>
            <div class="table-responsive">
              <table
                id="qerList"
                style="width:100%"
                class="table row-border table-hover dataTable"
                role="grid"
              >
                <thead>
                  <tr>
                    <th>Id</th>
                    <th>Oluşturucu Id</th>
                    <th>Soru Id</th>
                    <th>Değerlendrici Id</th>
                    <th>İsteği Yapan</th>
                    <th>Değerlendirici</th>
                    <th>Branş/Ders</th>
                    <th>Kod</th>
                    <th>Yorum</th>
                    <th>Puan</th>
                    <th>Kayıt Tarihi</th>
                    <th>Aksiyon</th>
                  </tr>
                </thead>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import Auth from '../../services/AuthService'
import Constants, { MessengerConstants } from '../../helpers/constants'
import tr from '../../helpers/dataTablesTurkish'
import Messenger from '../../helpers/messenger'
import vSelect from 'vue-select'
import BranchService from '../../services/BranchService'
import UserService from '../../services/UserService'

let qTable = null

export default {
  name: 'QuestionEvaluationRequestList',
  components: { vSelect },
  data () {
    return {
      branches: [],
      selectedBranch: '',
      user: ''
    }
  },
  beforeRouteEnter (to, from, next) {
    Promise.all([BranchService.getBranches(), UserService.findById(Auth.getUserId())])
      .then(([branches, user]) => {
        next(vm => {
          vm.user = user
          // Burada kullanıcı sosyal bilgiler öğretmeni ise
          // hem sosyal bilgilere hem de inklap tarihi dersine soru yazabilmeli
          if (!vm.$isInRole('admin') && user.branch.code === 'SB') {
            vm.branches = branches.filter(b => b.code === 'SB' || b.code === 'İTA')
          } else {
            vm.branches = branches
          }
        })
      })
      .catch(() => {
        Messenger.showError(MessengerConstants.errorMessage)
        next(from.path)
      })
  },
  computed: {
    checkPermission () {
      if (this.user) {
        return this.$isInRole('admin') || this.user.branch.code === 'SB'
      }
      return false
    }
  },
  mounted () {
    const vm = this
    const table = $('#qerList')
      .on('preXhr.dt', (e, settings, data) => {
        // Bu event sunucuya datatable üzerinden veri gitmeden önce
        // yeni parametre eklemek için ateşleniyor
        data.branch_id = vm.selectedBranch
      })
      .DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: {
          url: `${vm.$getBasePath()}/api/questions/evaluation_requests`,
          dataType: 'json',
          type: 'POST',
          beforeSend (xhr) {
            Auth.check()
            const token = localStorage.getItem(Constants.accessToken)
            xhr.setRequestHeader('Authorization', `Bearer ${token}`)
          },
          error: function (jqXHR, textStatus, errorThrown) {
            console.log(errorThrown)
            Messenger.showError(MessengerConstants.errorMessage)
          }
        },
        language: tr,
        columns: [
          {
            data: 'id',
            name: 'qer.id',
            visible: false
          },
          {
            data: 'question_id',
            name: 'qer.question_id',
            visible: false
          },
          {
            data: 'creator_id',
            name: 'qer.creator_id',
            visible: false
          },
          {
            data: 'elector_id',
            name: 'qer.elector_id',
            visible: false
          },
          {
            data: 'creator_name',
            name: 'creator.full_name',
            searchable: true
          },
          {
            data: 'elector_name',
            name: 'elector.full_name',
            searchable: true
          },
          {
            data: 'branch_name',
            name: 'b.name',
            searchable: true
          },
          {
            data: 'code',
            name: 'qer.code',
            className: 'text-center',
            searchable: true
          },
          {
            data: 'comment',
            name: 'qer.comment',
            searchable: true
          },
          {
            data: 'point',
            name: 'qer.point',
            searchable: true
          },
          {
            data: 'created_at',
            name: 'q.created_at',
            className: 'text-center',
            searchable: true
          },
          {
            data: '',
            className: 'text-center',
            width: '10%',
            render (data, type, row, meta) {
              return '<button class="btn btn-xs btn-info">Göster</button>'
            },
            searchable: false,
            orderable: false
          }
        ],
        retrieve: true,
        searching: true,
        paging: true
      })
    qTable = table

    // Tablo içindeki belli bir css sınıfına sahip bir butona basınca çalışacak event
    table.on('click', '.btn-info', (e) => {
      const data = table.row($(e.toElement).parents('tr')[0]).data()
      // console.log(data);
      vm.$router.push({
        name: 'questionEvaluation',
        params: { questionId: data.question_id, qerId: data.id },
        query: { code: data.code }
      })
    })

    // table.on('click', '.btn-warning', (e) => {
    //   let data = table.row($(e.toElement).parents('tr')[0]).data()
    //   // console.log(data);
    //   vm.showLearningOutcome(data.lo_id)
    // })
  },
  methods: {
    onSelectionChanged () {
      if (qTable) {
        qTable.ajax.reload()
      }
    } // ,
    // showLearningOutcome (loId) {
    //   LearningOutcomesService.findById(loId)
    //     .then(value => Messenger.showInfo(value.learning_outcome))
    //     .catch(reason => Messenger.showError(reason.message))
    // }
  }
}
</script>

<style lang="sass">
  @import '~datatables.net-bs/css/dataTables.bootstrap.min.css'
  @import '~datatables.net-responsive-bs/css/responsive.bootstrap.min.css'
</style>
