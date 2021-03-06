<!--
  - Bu yazılım Elektrik Elektronik Teknolojileri Alanı/Elektrik Öğretmeni Hakan GÜLEN tarafından geliştirilmiş olup geliştirilen bütün kaynak kodlar
  - Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0) ile lisanslanmıştır.
  - Ayrıntılı lisans bilgisi için https://creativecommons.org/licenses/by-nc-sa/4.0/legalcode.tr sayfasını ziyaret edebilirsiniz.2019
  -->

<template>
  <section class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="box">
          <div class="box-header with-border">
            <h4>Kullanıcı Listesi</h4>
          </div>
          <div class="box-body">
            <div
              class="table-responsive"
              :class="{ disabled : isApproving }"
            >
              <table
                id="userList"
                style="width:100%"
                class="table table-bordered table-hover dataTable"
                role="grid"
              >
                <thead>
                  <tr>
                    <th>Id</th>
                    <th>Ad Soyad</th>
                    <th>Telefon</th>
                    <th>Branş/Ders</th>
                    <th>Kurum</th>
                    <th>Onaylayan</th>
                    <th
                      data-type="date"
                      data-format="DD/MM/YYYY"
                    >
                      Kayıt Tarihi
                    </th>
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
import Constants from '../../helpers/constants'
import Auth from '../../services/AuthService'
import Messenger from '../../helpers/messenger'
import UserService from '../../services/UserService'

export default {
  name: 'UserList',
  data () {
    return {
      isApproving: false,
      userList: []
    }
  },
  mounted () {
    const vm = this
    let table = $('#userList')
      .DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: {
          url: `${vm.$getBasePath()}/api/users/passives`,
          dataType: 'json',
          type: 'POST',
          beforeSend(xhr) {
            Auth.check()
            const token = localStorage.getItem(Constants.accessToken)
            xhr.setRequestHeader('Authorization',
              `Bearer ${token}`)
          }
          // error: function (jqXHR, textStatus, errorThrown) {
          //   console.log(errorThrown)
          // }
        },
        language: {
          'sDecimal': ',',
          'sEmptyTable': 'Tabloda herhangi bir veri mevcut değil',
          'sInfo': '_TOTAL_ kayıttan _START_ - _END_ arasındaki kayıtlar gösteriliyor',
          'sInfoEmpty': 'Kayıt yok',
          'sInfoFiltered': '(_MAX_ kayıt içerisinden bulunan)',
          'sInfoPostFix': '',
          'sInfoThousands': '.',
          'sLengthMenu': 'Sayfada _MENU_ kayıt göster',
          'sLoadingRecords': 'Yükleniyor...',
          'sProcessing': 'İşleniyor...',
          'sSearch': 'Ara:',
          'sZeroRecords': 'Eşleşen kayıt bulunamadı',
          'oPaginate': {
            'sFirst': 'İlk',
            'sLast': 'Son',
            'sNext': 'Sonraki',
            'sPrevious': 'Önceki'
          },
          'oAria': {
            'sSortAscending': ': artan sütun sıralamasını aktifleştir',
            'sSortDescending': ': azalan sütun sıralamasını aktifleştir'
          },
          'select': {
            'rows': {
              '_': '%d kayıt seçildi',
              '0': '',
              '1': '1 kayıt seçildi'
            }
          }
        },
        columns: [
          {
            data: 'id',
            name: 'users.id',
            visible: false
          },
          {
            data: 'full_name',
            name: 'users.full_name',
            searchable: true
          },
          {
            data: 'phone',
            searchable: true
          },
          {
            data: 'branch_name',
            name: 'branches.name',
            searchable: true
          },
          {
            data: 'inst_name',
            name: 'institutions.name',
            searchable: true
          },
          {
            data: 'activator_name',
            name: 'um.full_name',
            searchable: false
          },
          {
            data: 'created_at',
            name: 'users.created_at',
            searchable: true
          },
          {
            data: '',
            className: 'text-center',
            width: '15%',
            render (data, type, row, meta) {
              return '<button class="btn btn-xs btn-info">Aktifleştir</button>'
            },
            searchable: false,
            orderable: false
          }
        ],
        retrieve: true,
        searching: true,
        paging: true
      })

    table.on('click', '.btn-info', (e) => {
      let data = table.row($(e.toElement).parents('tr')[0]).data()
      Messenger.showPrompt('Bu kullanıcı tekrar aktive etmek istediğinizden emin misiniz?', {
        cancel: 'İptal',
        ok: {
          text: 'Evet',
          value: true
        }
      }).then(value => {
        if (value) {
          UserService.reactivate(data.id)
            .then(resp => {
              Messenger.showInfoV2(resp.message)
                .then(() => table.ajax.reload())
            })
            .catch(err => Messenger.showError(err.message))
        }
      })
    })
  }
}
</script>

<style lang="sass">
  @import '~datatables.net-bs/css/dataTables.bootstrap.min.css'
  @import '~datatables.net-responsive-bs/css/responsive.bootstrap.min.css'
</style>
