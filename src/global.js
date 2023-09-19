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
      let highlightedTitle = '<a class="relative block w-full cursor-pointer py-6 pl-12 pr-40 after:absolute after:inset-0 after:pointer-events-none after:z-20 after:opacity-0 after:bg-brand hover:after:opacity-50 focus:after:opacity-50" href="' + (response[i].url ?? '#') + '">'
      if (response[i].image) {
        highlightedTitle += '<div class="absolute right-0 inset-y-0 w-160 z-10 after:w-160 after:h-full after:inset-0 after:absolute after:bg-gradient-to-r after:from-white/100 after:to-white/20"><img src="' + response[i].image + '" class="absolute w-full h-full object-cover object-center" /></div>';
      }
      highlightedTitle += '<span class="absolute left-8 top-8 inline-block w-20 h-20 z-30">' + response[i].icon + '</span><span class="inline-block pl-24 relative z-30">'
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

document.addEventListener('keydown', function(e) {
  if (e.key == 'Escape') {
    clearAutocompleteList()
  }
})

function clearAutocompleteList() {
  focusedResult = -1
  autocompleteResults.innerHTML = ''
  autocompleteResults.classList.add('hidden')
}

function highlightActiveResult(results) {
  if (!results) return false
  autocompleteResults.querySelectorAll('li').forEach(el => el.querySelector('a').classList.remove('after:!opacity-50'))
  if (focusedResult >= results.length) focusedResult = 0
  if (focusedResult < 0) focusedResult = (results.length - 1)
  results[focusedResult].querySelector('a').classList.add('after:!opacity-50')
}

// Game filters just reload the page on any change
if (gameFilters) {
  const inputs = gameFilters.querySelectorAll('input')

  for (let input of inputs) {
    input.addEventListener('change', function (e) {
      window.location.hash = ''
      gameFilters.submit()
    })
  }
}

let selectedItem = null

// Highlight item with ID matching hash on pageload
if (window.location.hash) {
  selectedItem = document.getElementById(window.location.hash.slice(1))
  selectedItem.classList.add('outline','outline-brand')
  document.addEventListener('click', function() {
    selectedItem.classList.remove('outline','outline-brand')
  }, {once : true})
}

// If the hash changes, update the highlighted item
window.addEventListener('hashchange', () => {
  selectedItem = document.getElementById(window.location.hash.slice(1))
  selectedItem.classList.add('outline','outline-brand')
  clearAutocompleteList()
  document.addEventListener('click', function() {
    selectedItem.classList.remove('outline','outline-brand')
  }, {once : true})
})
