import { Calendar } from '@fullcalendar/core'

const gameFilters = document.getElementById('game-filters')
const searchInput = document.getElementById('search')
const autocompleteResults = document.getElementById('search-autocomplete-results')
let focusedResult = -1

const handleInput = async function() {
  let query = this.value
  let li
  let body = {
    query: query
  }
  clearAutocompleteList()
  body[window.csrfTokenName] = window.csrfTokenValue
  if (query.length <= 2) return false;
  const request = await fetch("/actions/supersearch/search/get-search-results", {
    body: JSON.stringify(body),
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    }
  })
  let response = await request.json()
  clearAutocompleteList()
  console.log(response)

  if (response.length) {
    autocompleteResults.classList.remove('hidden')
    for(let i = 0; i < response.length; i++) {
      li = document.createElement('li')
      let highlightedTitle = '<a class="relative block w-full cursor-pointer py-6 pl-12 pr-40" title="' + response[i].title + '" href="' + (response[i].url ?? '#') + '">'
      highlightedTitle += '<span class="absolute left-8 top-8 inline-block w-20 h-20">' + response[i].icon + '</span><span class="inline-block pl-24">'
      let queryIntersection = response[i].title.toLowerCase().indexOf(query.toLowerCase())
      if (response[i].title.toLowerCase().indexOf(query.toLowerCase()) !== -1) {
        highlightedTitle += response[i].title.substr(0, queryIntersection) + '<strong>' + 
                            response[i].title.substr(queryIntersection, query.length) + '</strong>' + 
                            response[i].title.substr(queryIntersection + query.length) + '</span></a>'
      } else {
        highlightedTitle += response[i].title + '</span></a>'
      }
      li.innerHTML = highlightedTitle
      autocompleteResults.appendChild(li)
    }
  }
}

searchInput.addEventListener('input', handleInput)

searchInput.addEventListener('keydown', function(e) {
  let results = autocompleteResults.getElementsByTagName('li')
  if (e.key == 'ArrowDown') {
    focusedResult++
    highlightActiveResult(results)
  } else if (e.key == 'ArrowUp') {
    focusedResult--
    highlightActiveResult(results)
  } else if (e.key == 'Enter') {
    if (focusedResult > -1) {
      e.preventDefault()
      if (results) results[focusedResult].firstChild.click()
    }
  }
})

function clearAutocompleteList() {
  focusedResult = -1
  autocompleteResults.innerHTML = ''
  autocompleteResults.classList.add('hidden')
}

function highlightActiveResult(results) {
  if (!results) return false
  autocompleteResults.querySelectorAll('li').forEach(el => el.classList.remove('bg-brand','text-white'))
  if (focusedResult >= results.length) focusedResult = 0
  if (focusedResult < 0) focusedResult = (results.length - 1)
  results[focusedResult].classList.add('bg-brand','text-white')
}

if (gameFilters) {
  const inputs = gameFilters.querySelectorAll('input')

  for (let input of inputs) {
    input.addEventListener('change', function (e) {
      gameFilters.submit()
    })
  }
}

let selectedItem = null

if (window.location.hash) {
  selectedItem = document.getElementById(window.location.hash.slice(1))
  selectedItem.classList.add('outline','outline-brand')
  document.addEventListener('click', function() {
    selectedItem.classList.remove('outline','outline-brand')
  }, {once : true})
}

window.addEventListener('hashchange', () => {
  selectedItem = document.getElementById(window.location.hash.slice(1))
  selectedItem.classList.add('outline','outline-brand')
  clearAutocompleteList()
  document.addEventListener('click', function() {
    selectedItem.classList.remove('outline','outline-brand')
  }, {once : true})
})
