/*
 *  Bu yazılım Elektrik Elektronik Teknolojileri Alanı/Elektrik Öğretmeni Hakan GÜLEN tarafından geliştirilmiş olup
 *  geliştirilen bütün kaynak kodlar
 *  Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0) ile lisanslanmıştır.
 *   Ayrıntılı lisans bilgisi için https://creativecommons.org/licenses/by-nc-sa/4.0/legalcode.tr sayfasını ziyaret edebilirsiniz.2019
 */

/*
 * Bu yazılım Elektrik Elektronik Teknolojileri Alanı/Elektrik Öğretmeni Hakan GÜLEN tarafından geliştirilmiş olup geliştirilen bütün kaynak kodlar
 * Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0) ile lisanslanmıştır.
 * Ayrıntılı lisans bilgisi için https://creativecommons.org/licenses/by-nc-sa/4.0/legalcode.tr sayfasını ziyaret edebilirsiniz.2019
 */

import http from '../helpers/axios'
import Messenger from '../helpers/messenger'
import { MessengerConstants } from '../helpers/constants'

const BranchService = {
  getAllForRegisterReq (name) {
    return new Promise((resolve, reject) => {
      http.get('/auth/branches')
        .then(response => { resolve(response.data) })
        .catch(error => { reject(error) })
    })
  },
  getBranchesWithStats () {
    return new Promise((resolve, reject) => {
      http.get('/branches/with_stats').then(response => {
        if (response === undefined) reject(new Error('Tanımsız cevap'))
        resolve(response.data)
      })
        .catch(error => {
          reject(error)
        })
    })
  },
  getBranches () {
    return new Promise((resolve, reject) => {
      http.get('/branches').then(response => {
        if (response === undefined) reject(new Error('Tanımsız cevap'))
        resolve(response.data)
      })
        .catch(error => {
          reject(error)
        })
    })
  },
  save: function (branch, callback) {
    http.post('/branches', branch)
      .then(response => {
        if (response === undefined) return
        callback(response.data)
      })
      .catch(error => {
        Messenger.showError(MessengerConstants.errorMessage)
        console.log(error)
      })
  },
  update (id, branch, callback) {
    http.put(`/branches/${id}`, branch)
      .then(response => {
        if (response === undefined) return
        callback(response.data)
      })
      .catch(error => {
        Messenger.showError(MessengerConstants.errorMessage)
        console.log(error)
      })
  },
  delete (id, callback) {
    http.delete(`/branches/${id}`)
      .then(response => {
        if (response === undefined) return
        callback(response.data)
      })
      .catch(error => {
        Messenger.showError(MessengerConstants.errorMessage)
        console.log(error)
      })
  }
}

export default BranchService
