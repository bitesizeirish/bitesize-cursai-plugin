Vue.component('bitesize-audio-button', {
    props: {
        soundId: Number,
        presetAudioText: {
            type: String,
            default: null
        },
        loadSoundInformation: {
            type: Boolean,
            default: true
        }
    },
    data: function() {
        return {
            audio: null,
            error: null,
            isPlaying: false, 
            sound: {
                soundId: null
            }
        }
    },
    //template: '#bitesize-audio-button',
    template: `
        <div class="bitesize-audio" :class="'soundid-' + sound.soundId">	    	
	        <div class="bitesize-audio__left">
                <button class="audio-button"
                    @click="handleClick"
                    :key="loadedSoundId"
                >
                    <span class="icon is-large" v-if="!isPlaying">
                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#fbfadc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>
                    </span>
                    <span class="icon is-large" v-if="isPlaying">
                        <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#fbfadc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><rect x="9" y="9" width="6" height="6"></rect></svg>
                    </span>
                </button>
                <div class="bitesize-audio__share">
                    <a v-bind:href="inirishUrl" class="is-small is-link" target="_blank">Share</a>
                </div>
            </div>
	        <div class="bitesize-audio__content">
	            <p class="bitesize-audio__irish" v-if="audioText" v-html="audioText"></p>
	            <p class="bitesize-audio__translation" v-if="translation">
	              {{ translation }}
	            </p>
              <p class="bitesize-audio__pronunciation has-text-grey has-text-weight-light" v-if="pronunciation">
                /{{ pronunciation }}/
              </p>
              <p v-if="error">{{ error }}</p>
	        </div>
            <div class="bitesize-audio__admin" style="display: none">
                #{{ sound.soundId }}
            </div>
	    </div>
    `,
    computed: {
        loadedSoundId: function() {
            if (this.sound === null) {
                return null;
            }

            return this.sound.soundId;
        },
        soundUrl: function() {
            // cache is a random cache buster
            return 'https://bitesizesounds.ams3.cdn.digitaloceanspaces.com/mp3/' + this.soundId + '.mp3';
        },
        inirishUrl: function() {
            return 'https://inirish.bitesize.irish/how-to-say/' + this.soundId
        },
        audioText: function() {
            if (this.presetAudioText !== null) {
                return this.presetAudioText;
            }
            if (this.sound && this.sound.label && this.sound.label.trim() !== '') {
                return this.sound.label; // This might contain HTML, v-html will render it
            }
            if (this.sound && this.sound.text) {
                return this.sound.text; // This is plain Irish text
            }

            return 'loading phrase...';
        },
        pronunciation: function() {
            if (this.sound.soundId === null) {
                return 'loading pronunciation...';
            }

            return this.sound.pronunciation;
        },
        translation: function() {
            if (this.sound.soundId === null) {
                return 'loading translation...';
            }

            return this.sound.translation;
        },
        audioObject: function() {
            let audio = new Audio(this.soundUrl);
            audio.addEventListener('ended', () => {
                this.isPlaying = false;
            });
            return audio;
        }
    },
    mounted: function() {
        this.audio = this.audioObject;
        
        // Check for prehydrated JSON data from server-side rendering
        var prehydratedData = this.loadPrehydratedData();
        if (prehydratedData) {
            this.sound = prehydratedData;
            console.log('[Bitesize Audio] Using prehydrated data for sound ID:', prehydratedData.soundId || prehydratedData.id);
            return; // SUCCESS
        }
        
        // SECURITY: No client-side fallback - API requires authentication
        // If no prehydrated data, show error message
        console.error('[Bitesize Audio] No prehydrated data found. Cannot load sound metadata.');
        this.error = 'Failed to load sound data. Please try refreshing the page.';
    },
    methods: {
        loadPrehydratedData: function() {
            // Find the container element (the div wrapping this component)
            var container = this.$el.parentElement;
            if (!container || !container.id) {
                console.warn('[Bitesize Audio] Container element not found or has no ID');
                return null;
            }
            
            // Look for the prehydrated data script tag
            var dataScriptId = container.id + '-data';
            var dataScript = document.getElementById(dataScriptId);
            
            if (!dataScript) {
                console.warn('[Bitesize Audio] No prehydrated data script found for:', dataScriptId);
                return null;
            }
            
            try {
                var data = JSON.parse(dataScript.textContent);
                return data;
            } catch (e) {
                console.error('[Bitesize Audio] Failed to parse prehydrated data:', e);
                return null;
            }
        },
        handleClick: function() {
            if (this.isPlaying) {
                this.stopAudio();
            } else {
                this.playAudio();
            }
        },
        playAudio: function() {
            this.audio.play()
                .then(() => {
                    this.isPlaying = true;
                })
                .catch((error) => {
                    console.error('Error playing audio:', error);
                });
        },
        stopAudio: function() {
            this.audio.pause();
            this.audio.currentTime = 0;
            this.isPlaying = false;
        },
        // fetchSoundObject() method REMOVED for security:
        // API requires X-API-Key and X-Client-Name headers which cannot be exposed to browser.
        // All sound metadata must be prehydrated server-side.
    },
    watch: {
        soundId: function() {
            this.stopAudio();
            this.audio = this.audioObject;
            // Note: Sound ID changes require page reload to fetch new metadata server-side
            this.error = 'Sound ID changed. Please refresh the page.';
        },
    },
});
